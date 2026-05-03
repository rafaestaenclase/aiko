<?php
require './BD/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

match($action) {
    'menu'              => getMenu(),
    'table'             => getTable(),
    'kitchen'           => getKitchen(),
    'create_order'      => createOrder(),
    'update_item'       => updateItem(),
    'update_product'    => updateProduct(),
    'delete_product'    => deleteProduct(),
    default             => jsonResponse(['error' => 'Acción no válida'], 404)
};

// ── PÚBLICA ──────────────────────────────────────────────

function getMenu() {
    global $pdo;
    assertMethod('GET');

    $stmt = $pdo->query('
        SELECT c.id AS category_id, c.name AS category, c.display_order,
               p.id, p.name, p.description, p.price, p.available
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id
        ORDER BY c.display_order, p.name
    ');

    $menu = [];
    foreach ($stmt->fetchAll() as $row) {
        $cid = $row['category_id'];
        if (!isset($menu[$cid])) {
            $menu[$cid] = [
                'category'      => $row['category'],
                'display_order' => $row['display_order'],
                'products'      => [],
            ];
        }
        if ($row['id']) {
            $menu[$cid]['products'][] = [
                'id'          => $row['id'],
                'name'        => $row['name'],
                'description' => $row['description'],
                'price'       => $row['price'],
                'available'   => (bool) $row['available'],
            ];
        }
    }

    jsonResponse(array_values($menu));
}

function getTable() {
    global $pdo;
    assertMethod('GET');

    $qr = $_GET['qr'] ?? '';
    if (!$qr) jsonResponse(['error' => 'QR requerido'], 400);

    $stmt = $pdo->prepare('SELECT id, name FROM tables WHERE qr_code = ?');
    $stmt->execute([$qr]);
    $table = $stmt->fetch();

    if (!$table) jsonResponse(['error' => 'Mesa no encontrada'], 404);

    jsonResponse($table);
}

function getKitchen() {
    global $pdo;
    assertMethod('GET');

    $stmt = $pdo->query('
        SELECT
            oi.id, oi.quantity, oi.notes, oi.status, oi.created_at,
            p.name AS product,
            t.name AS table_name,
            o.id   AS order_id
        FROM order_items oi
        JOIN orders   o ON oi.order_id   = o.id
        JOIN tables   t ON o.table_id    = t.id
        JOIN products p ON oi.product_id = p.id
        WHERE oi.status = "PENDING"
        ORDER BY oi.created_at ASC
    ');

    jsonResponse($stmt->fetchAll());
}

function createOrder() {
    global $pdo;
    assertMethod('POST');

    $body     = getBody();
    $table_id = (int) ($body['table_id'] ?? 0);
    $items    = $body['items'] ?? [];

    if (!$table_id || empty($items))
        jsonResponse(['error' => 'Datos incompletos'], 400);

    $stmt = $pdo->prepare('SELECT id FROM tables WHERE id = ?');
    $stmt->execute([$table_id]);
    if (!$stmt->fetch()) jsonResponse(['error' => 'Mesa no válida'], 404);

    $ids          = array_map(fn($i) => (int) $i['product_id'], $items);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt         = $pdo->prepare("SELECT id FROM products WHERE id IN ($placeholders) AND available = TRUE");
    $stmt->execute($ids);
    $validIds = array_column($stmt->fetchAll(), 'id');

    foreach ($ids as $id)
        if (!in_array($id, $validIds))
            jsonResponse(['error' => "Producto $id no disponible"], 400);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO orders (table_id) VALUES (?)');
        $stmt->execute([$table_id]);
        $order_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare('
            INSERT INTO order_items (order_id, product_id, quantity, notes)
            VALUES (?, ?, ?, ?)
        ');
        foreach ($items as $item) {
            $stmt->execute([
                $order_id,
                (int)   $item['product_id'],
                (int)  ($item['quantity'] ?? 1),
                isset($item['notes']) ? htmlspecialchars($item['notes']) : null,
            ]);
        }

        $pdo->commit();
        jsonResponse(['order_id' => $order_id], 201);

    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Error al crear el pedido'], 500);
    }
}

// ── PROTEGIDAS (requieren token) ─────────────────────────

function updateItem() {
    global $pdo;
    assertMethod('PATCH');
    requireAdminToken($pdo);

    $body   = getBody();
    $id     = (int) ($body['id'] ?? 0);
    $status = $body['status'] ?? '';

    if (!$id || !in_array($status, ['PENDING', 'READY']))
        jsonResponse(['error' => 'Datos inválidos'], 400);

    $stmt = $pdo->prepare('UPDATE order_items SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);

    if ($stmt->rowCount() === 0)
        jsonResponse(['error' => 'Línea no encontrada'], 404);

    // Si todos los items están READY → pedido DONE
    $stmt = $pdo->prepare('SELECT order_id FROM order_items WHERE id = ?');
    $stmt->execute([$id]);
    $order_id = $stmt->fetchColumn();

    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM order_items
        WHERE order_id = ? AND status != "READY"
    ');
    $stmt->execute([$order_id]);
    if ($stmt->fetchColumn() == 0)
        $pdo->prepare('UPDATE orders SET status = "DONE" WHERE id = ?')
            ->execute([$order_id]);

    jsonResponse(['ok' => true]);
}

function updateProduct() {
    global $pdo;
    assertMethod('PATCH');
    requireAdminToken($pdo);

    $body = getBody();
    $id   = (int) ($body['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID requerido'], 400);

    $allowed = ['name', 'description', 'price', 'available', 'category_id'];
    $fields  = [];
    $values  = [];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $body)) {
            $fields[] = "$field = ?";
            $values[] = $body[$field];
        }
    }

    if (empty($fields)) jsonResponse(['error' => 'Nada que actualizar'], 400);

    $values[] = $id;
    $pdo->prepare('UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = ?')
        ->execute($values);

    jsonResponse(['ok' => true]);
}

function deleteProduct() {
    global $pdo;
    assertMethod('DELETE');
    requireAdminToken($pdo);

    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID requerido'], 400);

    $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0)
        jsonResponse(['error' => 'Producto no encontrado'], 404);

    jsonResponse(['ok' => true]);
}

// ── HELPERS ──────────────────────────────────────────────

function assertMethod(string $expected) {
    if ($_SERVER['REQUEST_METHOD'] !== $expected)
        jsonResponse(['error' => "Se esperaba $expected"], 405);
}
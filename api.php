<?php
// ============================================
// কৃষি মিত্র - API Handler (api.php)
// ============================================
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$action = $_REQUEST['action'] ?? '';
$db = getDB();

switch($action) {

    // ===== AUTH =====
    case 'buyer_register':
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $address = trim($_POST['address'] ?? '');
        $district = trim($_POST['district'] ?? '');

        if (!$name || !$email || !$phone || !$password)
            jsonResponse(['success'=>false,'message'=>'সব তথ্য পূরণ করুন']);

        $stmt = $db->prepare("SELECT id FROM buyers WHERE email=? OR phone=?");
        $stmt->execute([$email, $phone]);
        if ($stmt->fetch()) jsonResponse(['success'=>false,'message'=>'এই ইমেইল বা ফোন নম্বর আগেই নিবন্ধিত']);

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO buyers (name,email,phone,password,address,district) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$name,$email,$phone,$hashed,$address,$district]);
        jsonResponse(['success'=>true,'message'=>'নিবন্ধন সফল হয়েছে!']);

    case 'buyer_login':
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = $db->prepare("SELECT * FROM buyers WHERE email=? AND is_active=1");
        $stmt->execute([$email]);
        $buyer = $stmt->fetch();
        if (!$buyer || !password_verify($password, $buyer['password']))
            jsonResponse(['success'=>false,'message'=>'ইমেইল বা পাসওয়ার্ড ভুল']);
        $_SESSION['buyer_id'] = $buyer['id'];
        unset($buyer['password']);
        jsonResponse(['success'=>true,'user'=>$buyer,'message'=>'লগইন সফল']);

    case 'farmer_register':
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $address = trim($_POST['address'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $nid = trim($_POST['nid_number'] ?? '');

        if (!$name || !$email || !$phone || !$password)
            jsonResponse(['success'=>false,'message'=>'সব তথ্য পূরণ করুন']);

        $stmt = $db->prepare("SELECT id FROM farmers WHERE email=? OR phone=?");
        $stmt->execute([$email, $phone]);
        if ($stmt->fetch()) jsonResponse(['success'=>false,'message'=>'এই ইমেইল বা ফোন নম্বর আগেই নিবন্ধিত']);

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO farmers (name,email,phone,password,address,district,nid_number) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$name,$email,$phone,$hashed,$address,$district,$nid]);
        jsonResponse(['success'=>true,'message'=>'নিবন্ধন সফল! অনুমোদনের জন্য অপেক্ষা করুন।']);

    case 'farmer_login':
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = $db->prepare("SELECT * FROM farmers WHERE email=? AND is_active=1");
        $stmt->execute([$email]);
        $farmer = $stmt->fetch();
        if (!$farmer || !password_verify($password, $farmer['password']))
            jsonResponse(['success'=>false,'message'=>'ইমেইল বা পাসওয়ার্ড ভুল']);
        if (!$farmer['is_verified'])
            jsonResponse(['success'=>false,'message'=>'আপনার অ্যাকাউন্ট এখনও অনুমোদিত হয়নি']);
        $_SESSION['farmer_id'] = $farmer['id'];
        unset($farmer['password']);
        jsonResponse(['success'=>true,'user'=>$farmer,'message'=>'লগইন সফল']);

    case 'admin_login':
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = $db->prepare("SELECT * FROM admins WHERE email=?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        if (!$admin || !password_verify($password, $admin['password']))
            jsonResponse(['success'=>false,'message'=>'ইমেইল বা পাসওয়ার্ড ভুল']);
        $_SESSION['admin_id'] = $admin['id'];
        unset($admin['password']);
        jsonResponse(['success'=>true,'user'=>$admin,'message'=>'এডমিন লগইন সফল']);

    case 'logout':
        session_destroy();
        jsonResponse(['success'=>true]);

    // ===== PRODUCTS =====
    case 'get_products':
        $cat = $_GET['category'] ?? '';
        $search = $_GET['search'] ?? '';
        $where = "WHERE p.is_approved=1 AND p.is_active=1 AND p.available_quantity > 0";
        $params = [];
        if ($cat) { $where .= " AND p.category_id=?"; $params[] = $cat; }
        if ($search) { $where .= " AND (p.name_bn LIKE ? OR p.name_en LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        $stmt = $db->prepare("SELECT p.*, c.name_bn as category_name, f.name as farmer_name, f.district as farmer_district
            FROM products p JOIN categories c ON p.category_id=c.id JOIN farmers f ON p.farmer_id=f.id $where ORDER BY p.created_at DESC");
        $stmt->execute($params);
        jsonResponse(['success'=>true,'products'=>$stmt->fetchAll()]);

    case 'get_product':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT p.*, c.name_bn as category_name, f.name as farmer_name, f.district, f.phone as farmer_phone
            FROM products p JOIN categories c ON p.category_id=c.id JOIN farmers f ON p.farmer_id=f.id
            WHERE p.id=? AND p.is_approved=1");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        if (!$product) jsonResponse(['success'=>false,'message'=>'পণ্য পাওয়া যায়নি']);
        // Get reviews
        $stmt2 = $db->prepare("SELECT r.*, b.name as buyer_name FROM reviews r JOIN buyers b ON r.buyer_id=b.id WHERE r.product_id=? ORDER BY r.created_at DESC LIMIT 5");
        $stmt2->execute([$id]);
        $product['reviews'] = $stmt2->fetchAll();
        jsonResponse(['success'=>true,'product'=>$product]);

    case 'get_categories':
        $stmt = $db->query("SELECT * FROM categories WHERE is_active=1");
        jsonResponse(['success'=>true,'categories'=>$stmt->fetchAll()]);

    case 'add_product':
        if (!isLoggedIn('farmer')) jsonResponse(['success'=>false,'message'=>'লগইন করুন']);
        $farmer_id = $_SESSION['farmer_id'];
        $name_bn = trim($_POST['name_bn'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $our_price = (float)($_POST['our_price'] ?? 0);
        $market_price = (float)($_POST['market_price'] ?? 0);
        $available_quantity = (float)($_POST['available_quantity'] ?? 0);
        $unit = $_POST['unit'] ?? 'কেজি';
        $description = trim($_POST['description'] ?? '');
        $is_organic = (int)($_POST['is_organic'] ?? 0);

        if (!$name_bn || !$category_id || !$our_price || !$available_quantity)
            jsonResponse(['success'=>false,'message'=>'সব তথ্য পূরণ করুন']);

        $image1 = $image2 = $image3 = null;
        if (!empty($_FILES['image1']['name'])) $image1 = uploadImage($_FILES['image1']);
        if (!empty($_FILES['image2']['name'])) $image2 = uploadImage($_FILES['image2']);
        if (!empty($_FILES['image3']['name'])) $image3 = uploadImage($_FILES['image3']);

        $stmt = $db->prepare("INSERT INTO products (farmer_id,category_id,name_bn,our_price,market_price,available_quantity,unit,description,is_organic,image1,image2,image3) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$farmer_id,$category_id,$name_bn,$our_price,$market_price,$available_quantity,$unit,$description,$is_organic,$image1,$image2,$image3]);
        jsonResponse(['success'=>true,'message'=>'পণ্য যুক্ত হয়েছে, অনুমোদনের অপেক্ষায় আছে']);

    case 'farmer_products':
        if (!isLoggedIn('farmer')) jsonResponse(['success'=>false,'message'=>'লগইন করুন']);
        $stmt = $db->prepare("SELECT p.*, c.name_bn as category_name FROM products p JOIN categories c ON p.category_id=c.id WHERE p.farmer_id=? ORDER BY p.created_at DESC");
        $stmt->execute([$_SESSION['farmer_id']]);
        jsonResponse(['success'=>true,'products'=>$stmt->fetchAll()]);

    case 'delete_product':
        if (!isLoggedIn('farmer')) jsonResponse(['success'=>false,'message'=>'লগইন করুন']);
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("UPDATE products SET is_active=0 WHERE id=? AND farmer_id=?");
        $stmt->execute([$id, $_SESSION['farmer_id']]);
        jsonResponse(['success'=>true,'message'=>'পণ্য মুছে ফেলা হয়েছে']);

    // ===== ORDERS =====
    case 'place_order':
        if (!isLoggedIn('buyer')) jsonResponse(['success'=>false,'message'=>'অর্ডার করতে লগইন করুন']);
        $buyer_id = $_SESSION['buyer_id'];
        $items = json_decode($_POST['items'] ?? '[]', true);
        $payment_method = $_POST['payment_method'] ?? '';
        $payment_number = $_POST['payment_number'] ?? '';
        $delivery_address = trim($_POST['delivery_address'] ?? '');
        $delivery_district = trim($_POST['delivery_district'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (empty($items)) jsonResponse(['success'=>false,'message'=>'কার্টে কোন পণ্য নেই']);
        if (!$delivery_address) jsonResponse(['success'=>false,'message'=>'ডেলিভারি ঠিকানা দিন']);

        // Calculate delivery charge
        $stmt = $db->prepare("SELECT charge FROM delivery_charges WHERE district=?");
        $stmt->execute([$delivery_district]);
        $dc = $stmt->fetch();
        $delivery_charge = $dc ? $dc['charge'] : 80;

        $total = 0;
        $orderItems = [];
        foreach ($items as $item) {
            $stmt = $db->prepare("SELECT * FROM products WHERE id=? AND is_approved=1 AND is_active=1");
            $stmt->execute([$item['id']]);
            $product = $stmt->fetch();
            if (!$product) continue;
            $qty = (float)$item['qty'];
            $price = $product['our_price'] * $qty;
            $total += $price;
            $orderItems[] = ['product_id'=>$product['id'],'farmer_id'=>$product['farmer_id'],'quantity'=>$qty,'unit_price'=>$product['our_price'],'total_price'=>$price];
        }

        $grand_total = $total + $delivery_charge;
        $order_number = generateOrderNumber();

        $stmt = $db->prepare("INSERT INTO orders (order_number,buyer_id,total_amount,delivery_charge,grand_total,payment_method,payment_number,delivery_address,delivery_district,notes) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$order_number,$buyer_id,$total,$delivery_charge,$grand_total,$payment_method,$payment_number,$delivery_address,$delivery_district,$notes]);
        $order_id = $db->lastInsertId();

        foreach ($orderItems as $oi) {
            $stmt = $db->prepare("INSERT INTO order_items (order_id,product_id,farmer_id,quantity,unit_price,total_price) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$order_id,$oi['product_id'],$oi['farmer_id'],$oi['quantity'],$oi['unit_price'],$oi['total_price']]);
            // Update stock
            $db->prepare("UPDATE products SET available_quantity = available_quantity - ? WHERE id=?")->execute([$oi['quantity'],$oi['product_id']]);
        }
        jsonResponse(['success'=>true,'order_number'=>$order_number,'message'=>'অর্ডার সফলভাবে দেওয়া হয়েছে!']);

    case 'my_orders':
        if (!isLoggedIn('buyer')) jsonResponse(['success'=>false,'message'=>'লগইন করুন']);
        $stmt = $db->prepare("SELECT o.*, (SELECT COUNT(*) FROM order_items WHERE order_id=o.id) as item_count FROM orders o WHERE o.buyer_id=? ORDER BY o.created_at DESC");
        $stmt->execute([$_SESSION['buyer_id']]);
        jsonResponse(['success'=>true,'orders'=>$stmt->fetchAll()]);

    case 'order_detail':
        $order_id = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT o.*, b.name as buyer_name, b.phone as buyer_phone FROM orders o JOIN buyers b ON o.buyer_id=b.id WHERE o.id=?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        if (!$order) jsonResponse(['success'=>false,'message'=>'অর্ডার পাওয়া যায়নি']);
        $stmt2 = $db->prepare("SELECT oi.*, p.name_bn, p.image1, p.unit FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE oi.order_id=?");
        $stmt2->execute([$order_id]);
        $order['items'] = $stmt2->fetchAll();
        jsonResponse(['success'=>true,'order'=>$order]);

    // ===== ADMIN =====
    case 'admin_dashboard':
        if (!isLoggedIn('admin')) jsonResponse(['success'=>false,'message'=>'এডমিন লগইন করুন']);
        $stats = [];
        $stats['total_orders'] = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
        $stats['total_revenue'] = $db->query("SELECT SUM(grand_total) FROM orders WHERE order_status != 'cancelled'")->fetchColumn() ?? 0;
        $stats['total_farmers'] = $db->query("SELECT COUNT(*) FROM farmers")->fetchColumn();
        $stats['total_buyers'] = $db->query("SELECT COUNT(*) FROM buyers")->fetchColumn();
        $stats['pending_products'] = $db->query("SELECT COUNT(*) FROM products WHERE is_approved=0 AND is_active=1")->fetchColumn();
        $stats['pending_orders'] = $db->query("SELECT COUNT(*) FROM orders WHERE order_status='pending'")->fetchColumn();
        $stats['pending_farmers'] = $db->query("SELECT COUNT(*) FROM farmers WHERE is_verified=0")->fetchColumn();
        $recent_orders = $db->query("SELECT o.*, b.name as buyer_name FROM orders o JOIN buyers b ON o.buyer_id=b.id ORDER BY o.created_at DESC LIMIT 10")->fetchAll();
        jsonResponse(['success'=>true,'stats'=>$stats,'recent_orders'=>$recent_orders]);

    case 'admin_farmers':
        if (!isLoggedIn('admin')) jsonResponse(['success'=>false,'message'=>'লগইন করুন']);
        $stmt = $db->query("SELECT f.*, (SELECT COUNT(*) FROM products WHERE farmer_id=f.id) as product_count FROM farmers f ORDER BY f.created_at DESC");
        jsonResponse(['success'=>true,'farmers'=>$stmt->fetchAll()]);

    case 'verify_farmer':
        if (!isLoggedIn('admin')) jsonResponse(['success'=>false,'message'=>'লগইন করুন']);
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE farmers SET is_verified=1 WHERE id=?")->execute([$id]);
        jsonResponse(['success'=>true,'message'=>'কৃষক অনুমোদিত হয়েছে']);

    case 'admin_products':
        if (!isLoggedIn('admin')) jsonResponse(['success'=>false,'message'=>'লগইন করুন']);
        $status = $_GET['status'] ?? 'pending';
        $where = $status === 'pending' ? "WHERE p.is_approved=0 AND p.is_active=1" : "WHERE p.is_approved=1 AND p.is_active=1";
        $stmt = $db->query("SELECT p.*, c.name_bn as cat_name, f.name as farmer_name FROM products p JOIN categories c ON p.category_id=c.id JOIN farmers f ON p.farmer_id=f.id $where ORDER BY p.created_at DESC");
        jsonResponse(['success'=>true,'products'=>$stmt->fetchAll()]);

    case 'approve_product':
        if (!isLoggedIn('admin')) jsonResponse(['success'=>false,'message'=>'লগইন করুন']);
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE products SET is_approved=1 WHERE id=?")->execute([$id]);
        jsonResponse(['success'=>true,'message'=>'পণ্য অনুমোদিত হয়েছে']);

    case 'reject_product':
        if (!isLoggedIn('admin')) jsonResponse(['success'=>false,'message'=>'লগইন করুন']);
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE products SET is_active=0 WHERE id=?")->execute([$id]);
        jsonResponse(['success'=>true,'message'=>'পণ্য প্রত্যাখ্যান করা হয়েছে']);

    case 'admin_orders':
        if (!isLoggedIn('admin')) jsonResponse(['success'=>false,'message'=>'লগইন করুন']);
        $stmt = $db->query("SELECT o.*, b.name as buyer_name, b.phone as buyer_phone FROM orders o JOIN buyers b ON o.buyer_id=b.id ORDER BY o.created_at DESC");
        jsonResponse(['success'=>true,'orders'=>$stmt->fetchAll()]);

    case 'update_order_status':
        if (!isLoggedIn('admin')) jsonResponse(['success'=>false,'message'=>'লগইন করুন']);
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $allowed = ['pending','confirmed','processing','shipped','delivered','cancelled'];
        if (!in_array($status, $allowed)) jsonResponse(['success'=>false,'message'=>'অবৈধ স্ট্যাটাস']);
        $db->prepare("UPDATE orders SET order_status=? WHERE id=?")->execute([$status,$id]);
        jsonResponse(['success'=>true,'message'=>'অর্ডার স্ট্যাটাস আপডেট হয়েছে']);

    case 'get_settings':
        if (!isLoggedIn('admin')) jsonResponse(['success'=>false,'message'=>'লগইন করুন']);
        $stmt = $db->query("SELECT * FROM settings");
        $settings = [];
        foreach ($stmt->fetchAll() as $row) $settings[$row['setting_key']] = $row['setting_value'];
        jsonResponse(['success'=>true,'settings'=>$settings]);

    case 'update_settings':
        if (!isLoggedIn('admin')) jsonResponse(['success'=>false,'message'=>'লগইন করুন']);
        foreach ($_POST as $key => $value) {
            if ($key === 'action') continue;
            $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$key,$value,$value]);
        }
        jsonResponse(['success'=>true,'message'=>'সেটিংস সংরক্ষিত হয়েছে']);

    case 'get_delivery_charges':
        $stmt = $db->query("SELECT * FROM delivery_charges ORDER BY district");
        jsonResponse(['success'=>true,'charges'=>$stmt->fetchAll()]);

    default:
        jsonResponse(['success'=>false,'message'=>'অবৈধ অনুরোধ']);
}
?>

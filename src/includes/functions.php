<?php
// Utility functions

function formatDate($date, $format = 'Y-m-d') {
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

function generateRoomID(){
    global $conn;
    $prefix = "ROOM";
    $sql = "SELECT MAX(room_id) as max_id FROM rooms";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);

    $next_id = ($row['max_id'] ?? 0) + 1;
    return $prefix . str_pad($next_id, 3, '0', STR_PAD_LEFT);
}

function formatCurrency($amount, $currency = null) {
    if ($currency === null) {
        $currency = PAYMENT_CURRENCY;
    }
    
    return 'Rp. ' . number_format($amount, 2);
}

function calculateNights($check_in, $check_out) {
    $check_in_obj = new DateTime($check_in);
    $check_out_obj = new DateTime($check_out);
    $interval = $check_in_obj->diff($check_out_obj);
    return $interval->days;
}

function calculateTotalPrice($price_per_night, $nights, $extras = 0, $include_tax = true) {
    $subtotal = ($price_per_night * $nights) + $extras;
    
    if ($include_tax) {
        return $subtotal * (1 + TAX_RATE);
    }
    
    return $subtotal;
}

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $string = '';
    
    for ($i = 0; $i < $length; $i++) {
        $string .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $string;
}

function generateBookingReference() {
    $prefix = 'BK';
    $timestamp = time();
    $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 4));
    
    return $prefix . $timestamp . $random;
}

function uploadFile($file, $destination = UPLOAD_DIR, $allowed_types = null, $max_size = null) {
    if ($allowed_types === null) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    }
    
    if ($max_size === null) {
        $max_size = MAX_FILE_SIZE;
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        
        $error_message = isset($error_messages[$file['error']]) 
            ? $error_messages[$file['error']] 
            : 'Unknown upload error';
            
        return ['status' => false, 'message' => $error_message];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        return ['status' => false, 'message' => 'File is too large'];
    }
    
    // Check file type
    if (!in_array($file['type'], $allowed_types)) {
        return ['status' => false, 'message' => 'File type not allowed'];
    }
    
    // Create destination directory if it doesn't exist
    if (!file_exists($destination)) {
        mkdir($destination, 0755, true);
    }
    
    // Generate a unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = uniqid() . '.' . $file_extension;
    $target_path = $destination . '/' . $new_filename;
    
    // Move the uploaded file
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return ['status' => true, 'filename' => $new_filename];
    } else {
        return ['status' => false, 'message' => 'Failed to move uploaded file'];
    }
}

function isRoomAvailable($room_id, $check_in, $check_out, $exclude_booking_id = 0) {
    global $conn;
    
    $exclude_clause = $exclude_booking_id > 0 ? " AND booking_id != $exclude_booking_id" : "";
    
    $query = "SELECT COUNT(*) as count FROM bookings 
             WHERE room_id = $room_id 
             AND booking_status IN ('confirmed', 'checked_in') 
             $exclude_clause
             AND (
                 (check_in_date <= '$check_in' AND check_out_date >= '$check_in') OR
                 (check_in_date <= '$check_out' AND check_out_date >= '$check_out') OR
                 (check_in_date >= '$check_in' AND check_out_date <= '$check_out')
             )";
             
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    
    return $row['count'] == 0;
}

function getRoomStatusInfo($status) {
    switch ($status) {
        case 'available':
            return ['text' => 'Available', 'class' => 'text-green-600'];
        case 'occupied':
            return ['text' => 'Occupied', 'class' => 'text-red-600'];
        case 'maintenance':
            return ['text' => 'Maintenance', 'class' => 'text-yellow-600'];
        default:
            return ['text' => ucfirst($status), 'class' => 'text-gray-600'];
    }
}

function getBookingStatusInfo($status) {
    switch ($status) {
        case 'pending':
            return ['text' => 'Pending', 'class' => 'text-yellow-600'];
        case 'confirmed':
            return ['text' => 'Confirmed', 'class' => 'text-green-600'];
        case 'checked_in':
            return ['text' => 'Checked In', 'class' => 'text-blue-600'];
        case 'checked_out':
            return ['text' => 'Checked Out', 'class' => 'text-gray-600'];
        case 'cancelled':
            return ['text' => 'Cancelled', 'class' => 'text-red-600'];
        default:
            return ['text' => ucfirst($status), 'class' => 'text-gray-600'];
    }
}

function getPaymentStatusInfo($status) {
    switch ($status) {
        case 'pending':
            return ['text' => 'Pending', 'class' => 'text-yellow-600'];
        case 'completed':
            return ['text' => 'Completed', 'class' => 'text-green-600'];
        case 'failed':
            return ['text' => 'Failed', 'class' => 'text-red-600'];
        case 'refunded':
            return ['text' => 'Refunded', 'class' => 'text-blue-600'];
        default:
            return ['text' => ucfirst($status), 'class' => 'text-gray-600'];
    }
}

function sendEmail($to, $subject, $message, $from_email = null, $from_name = null) {
    if ($from_email === null) {
        $from_email = EMAIL_FROM;
    }
    
    if ($from_name === null) {
        $from_name = EMAIL_NAME;
    }
    
    $headers = "From: $from_name <$from_email>\r\n";
    $headers .= "Reply-To: $from_email\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

function generatePagination($total_items, $items_per_page, $current_page, $url_pattern) {
    $total_pages = ceil($total_items / $items_per_page);
    
    if ($total_pages <= 1) {
        return '';
    }
    
    $html = '<div class="flex items-center justify-center space-x-1 mt-6">';
    
    // Previous button
    if ($current_page > 1) {
        $prev_url = sprintf($url_pattern, $current_page - 1);
        $html .= '<a href="' . $prev_url . '" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">Previous</a>';
    } else {
        $html .= '<span class="px-4 py-2 text-gray-400 bg-gray-100 rounded-md cursor-not-allowed">Previous</span>';
    }
    
    // Page number
    $range = 2;
    for ($i = max(1, $current_page - $range); $i <= min($total_pages, $current_page + $range); $i++) {
        if ($i == $current_page) {
            $html .= '<span class="px-4 py-2 text-white bg-blue-500 rounded-md">' . $i . '</span>';
        } else {
            $page_url = sprintf($url_pattern, $i);
            $html .= '<a href="' . $page_url . '" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">' . $i . '</a>';
        }
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $next_url = sprintf($url_pattern, $current_page + 1);
        $html .= '<a href="' . $next_url . '" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">Next</a>';
    } else {
        $html .= '<span class="px-4 py-2 text-gray-400 bg-gray-100 rounded-md cursor-not-allowed">Next</span>';
    }
    
    $html .= '</div>';
    
    return $html;
}

function truncateString($string, $length = 100, $append = '...') {
    if (strlen($string) <= $length) {
        return $string;
    }
    
    $string = substr($string, 0, $length);
    $string = rtrim($string);
    
    return $string . $append;
}

function getCurrentUrl($include_query_string = true) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $url = $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    
    if (!$include_query_string && strpos($url, '?') !== false) {
        $url = substr($url, 0, strpos($url, '?'));
    }
    
    return $url;
}

function getCurrentPage() {
    return basename($_SERVER['PHP_SELF'], '.php');
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function createSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9\-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    $string = trim($string, '-');
    return $string;
}

function getRoomTypes() {
    return [
        'Standard' => 'Standard Room',
        'Deluxe' => 'Deluxe Room',
        'Executive' => 'Executive Room',
    ];
}

function getRoomAmenities() {
    return [
        'wifi' => 'Free Wi-Fi',
        'ac' => 'Air Conditioning',
        'tv' => 'Flat-screen TV',
        'fridge' => 'Mini Fridge',
        'safe' => 'In-room Safe',
        'bath' => 'Private Bathroom',
        'balcony' => 'Balcony',
        'kitchen' => 'Kitchenette',
        'workspace' => 'Workspace',
        'breakfast' => 'Breakfast Included'
    ];
}

function getPaymentMethods() {
    return [
        'credit_card' => 'Credit Card',
        'debit_card' => 'Debit Card',
        'paypal' => 'PayPal',
        'bank_transfer' => 'Bank Transfer',
        'cash' => 'Cash'
    ];
}

function logActivity($action, $description, $user_id = null) {
    global $conn;
    
    if ($user_id === null && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    } else if ($user_id === null) {
        $user_id = 0; // Guest or system
    }
    
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $query = "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
             VALUES (?, ?, ?, ?, ?)";
             
    $stmt = $conn->prepare($query);
    $stmt->bind_param("issss", $user_id, $action, $description, $ip_address, $user_agent);
    
    return $stmt->execute();
}

function getUserById($user_id) {
    global $conn;
    
    $query = "SELECT * FROM users WHERE user_id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        return $result->fetch_assoc();
    }
    
    return null;
}

function emailExists($email, $exclude_user_id = 0) {
    global $conn;
    
    $query = "SELECT COUNT(*) as count FROM users WHERE email = ?";
    
    if ($exclude_user_id > 0) {
        $query .= " AND user_id != ?";
    }
    
    $stmt = $conn->prepare($query);
    
    if ($exclude_user_id > 0) {
        $stmt->bind_param("si", $email, $exclude_user_id);
    } else {
        $stmt->bind_param("s", $email);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'] > 0;
}

function usernameExists($username, $exclude_user_id = 0) {
    global $conn;
    
    $query = "SELECT COUNT(*) as count FROM users WHERE username = ?";
    
    if ($exclude_user_id > 0) {
        $query .= " AND user_id != ?";
    }
    
    $stmt = $conn->prepare($query);
    
    if ($exclude_user_id > 0) {
        $stmt->bind_param("si", $username, $exclude_user_id);
    } else {
        $stmt->bind_param("s", $username);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'] > 0;
}

function getAvailableRooms($check_in, $check_out, $room_type = '', $capacity = 0) {
    global $conn;
    
    $query = "SELECT * FROM rooms WHERE status = 'available'";
    
    if (!empty($room_type)) {
        $query .= " AND room_type = '$room_type'";
    }
    
    if ($capacity > 0) {
        $query .= " AND capacity >= $capacity";
    }
    
    if (!empty($check_in) && !empty($check_out)) {
        $query .= " AND room_id NOT IN (
            SELECT room_id FROM bookings 
            WHERE booking_status IN ('confirmed', 'checked_in') 
            AND (
                (check_in_date <= '$check_in' AND check_out_date >= '$check_in') OR
                (check_in_date <= '$check_out' AND check_out_date >= '$check_out') OR
                (check_in_date >= '$check_in' AND check_out_date <= '$check_out')
            )
        )";
    }
    
    $result = $conn->query($query);
    $rooms = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $rooms[] = $row;
        }
    }
    
    return $rooms;
}

function generateResetToken($user_id) {
    global $conn;
    
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $query = "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $user_id, $token, $expires);
    
    if ($stmt->execute()) {
        return $token;
    }
    
    return false;
}

function validateResetToken($token) {
    global $conn;
    
    $query = "SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        return $row['user_id'];
    }
    
    return false;
}

function getRoomById($room_id) {
    global $conn;
    
    $query = "SELECT * FROM rooms WHERE room_id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        return $result->fetch_assoc();
    }
    
    return null;
}

function getBookingById($booking_id) {
    global $conn;
    
    $query = "SELECT b.*, r.room_number, r.room_type, r.price_per_night, u.full_name, u.email 
             FROM bookings b
             JOIN rooms r ON b.room_id = r.room_id
             JOIN users u ON b.user_id = u.user_id
             WHERE b.booking_id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        return $result->fetch_assoc();
    }
    
    return null;
}

function getUserBookings($user_id, $status = '') {
    global $conn;
    
    $query = "SELECT b.*, r.room_number, r.room_type, r.image_url FROM bookings b JOIN rooms r ON b.room_id = r.room_id WHERE b.user_id = ? ORDER BY b.created_at DESC";
    
    if (!empty($status)) {
        $query .= " AND b.booking_status = ?";
    }
    
    // $query .= " ORDER BY b.created_at DESC";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($status)) {
        $stmt->bind_param("is", $user_id, $status);
    } else {
        $stmt->bind_param("i", $user_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $bookings = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $bookings[] = $row;
        }
    }
    
    return $bookings;
}

function cancelBooking($booking_id, $user_id) {
    global $conn;
    
    $query = "UPDATE bookings SET booking_status = 'cancelled' 
             WHERE booking_id = ? AND user_id = ? AND booking_status = 'confirmed'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    
    return $stmt->affected_rows > 0;
}

function getDashboardStats() {
    global $conn;
    
    $stats = [
        'total_rooms' => 0,
        'available_rooms' => 0,
        'occupied_rooms' => 0,
        'maintenance_rooms' => 0,
        'active_bookings' => 0,
        'pending_bookings' => 0,
        'revenue_today' => 0,
        'revenue_month' => 0
    ];
    
    // Get room stats
    $room_query = "SELECT 
                  COUNT(*) as total_rooms,
                  SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_rooms,
                  SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied_rooms,
                  SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_rooms
                  FROM rooms";
    $result = $conn->query($room_query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stats['total_rooms'] = $row['total_rooms'];
        $stats['available_rooms'] = $row['available_rooms'];
        $stats['occupied_rooms'] = $row['occupied_rooms'];
        $stats['maintenance_rooms'] = $row['maintenance_rooms'];
    }
    
    // Get booking stats
    $booking_query = "SELECT 
                    SUM(CASE WHEN booking_status IN ('confirmed', 'checked_in') THEN 1 ELSE 0 END) as active_bookings,
                    SUM(CASE WHEN booking_status = 'pending' THEN 1 ELSE 0 END) as pending_bookings
                    FROM bookings";
    $result = $conn->query($booking_query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stats['active_bookings'] = $row['active_bookings'];
        $stats['pending_bookings'] = $row['pending_bookings'];
    }
    
    // Get revenue stats
    $today = date('Y-m-d');
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    
    $revenue_today_query = "SELECT SUM(amount) as revenue FROM payments WHERE status = 'completed' AND DATE(payment_date) = '$today'";
    $result = $conn->query($revenue_today_query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stats['revenue_today'] = $row['revenue'] ?: 0;
    }
    
    $revenue_month_query = "SELECT SUM(amount) as revenue FROM payments WHERE status = 'completed' AND payment_date BETWEEN '$month_start' AND '$month_end'";
    $result = $conn->query($revenue_month_query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stats['revenue_month'] = $row['revenue'] ?: 0;
    }
    
    return $stats;
}

?>


<?php
// إعدادات الاتصال بقاعدة البيانات
$host = 'localhost';
$dbname = 'the-queen7';
$username = 'root';
$password = ''; // كلمة المرور الافتراضية في xampp غالباً فارغة

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // تجاهل أخطاء الاتصال مؤقتاً لتجنب إيقاف المنظومة في حال عدم وجود قاعدة البيانات حالياً
    // سيتم استخدام هذا الملف لاحقاً عند إنشاء الجداول
    $db_error = "لم يتم العثور على قاعدة البيانات، ولكن يمكنك تسجيل الدخول باستخدام الحساب الافتراضي.";
}
?>

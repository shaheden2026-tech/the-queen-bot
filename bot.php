<?php
// إعداد تقارير الأخطاء (يمكن إيقافها في بيئة الإنتاج)
error_reporting(E_ALL);
ini_set('display_errors', 0); // لا تعرض الأخطاء للمستخدم

// تضمين ملف الاتصال بقاعدة البيانات
require_once 'db.php'; 

// التوكنز ومفاتيح الـ API
$telegram_token = '8806077134:AAGMhaju_ndtdZkuG1GzGArxKYjPuhUywVA';
$gemini_api_key = trim('AQ.Ab8RN6IBqurT0LLT39dA6fxjUUrpSGwUbcYfasJoLBh5hHFoFg    ');

// استقبال البيانات القادمة من تيليجرام (Webhook)
$content = file_get_contents("php://input");
$update = json_decode($content, true);

// التأكد من وجود رسالة نصية
if (!$update || !isset($update['message']) || !isset($update['message']['text'])) {
    exit;
}

$message = $update['message'];
$chat_id = $message['chat']['id'];
$text = trim($message['text']);

if (empty($text)) {
    exit;
}

$reply = "";

// 1. تحقق من الكلمات المفتاحية الخاصة بمواعيد العمل
if (preg_match('/(مفتوح|مواعيد|ساعات العمل|امتى تفتحو|وقت الدوام|تفتحون|دوامكم)/iu', $text)) {
    $reply = "أهلاً بك في بوت The Queen! 👑\n\nنحن متواجدون لخدمتكم يومياً من الساعة 10:00 صباحاً وحتى 10:00 مساءً.\nنسعد بزيارتكم! ✨";
} 
else {
    // 2. البحث في قاعدة البيانات عن المنتجات
    // إزالة كلمات السؤال الشائعة لاستخراج اسم المنتج المراد البحث عنه
    $search_term = trim(preg_replace('/(هل يوجد|عندكم|فيه|بكم|اريد|أريد|استفسار عن|؟|\?)/iu', '', $text));
    
    $product_found = false;
    
    // إذا تبقى كلمة للبحث طولها أكثر من حرفين
    if (!empty($search_term) && mb_strlen($search_term) > 2 && isset($pdo)) {
        try {
            // البحث عن المنتج في الاسم أو التصنيف
            $stmt = $pdo->prepare("SELECT name, price, qty, color, size FROM products WHERE name LIKE ? OR category LIKE ? LIMIT 5");
            $stmt->execute(["%$search_term%", "%$search_term%"]);
            $products = $stmt->fetchAll();
            
            if (count($products) > 0) {
                $product_found = true;
                $reply = "أهلاً بك! إليك ما وجدته بخصوص طلبك (".$search_term."):\n\n";
                foreach ($products as $p) {
                    $status = ($p['qty'] > 0) ? "✅ متوفر ({$p['qty']} قطعة)" : "❌ غير متوفر حالياً";
                    $reply .= "👗 " . $p['name'] . "\n";
                    if (!empty($p['color'])) $reply .= "🎨 اللون: " . $p['color'] . "\n";
                    if (!empty($p['size']) && $p['size'] != '-') $reply .= "📏 المقاس: " . $p['size'] . "\n";
                    $reply .= "💰 السعر: " . $p['price'] . "\n"; 
                    $reply .= "📊 الحالة: " . $status . "\n";
                    $reply .= "──────────────\n";
                }
                $reply .= "لأي استفسار آخر أنا في الخدمة! 👑";
            }
        } catch (Exception $e) {
            // تجاهل أخطاء قاعدة البيانات للانتقال لنموذج الذكاء الاصطناعي كبديل
        }
    }
    
    // 3. إذا لم يكن سؤالاً عن مواعيد ولم نجد منتجاً مطابقاً، نستخدم Gemini API
    if (!$product_found) {
        $reply = askGemini($text, $gemini_api_key);
    }
}

// إرسال الرد النهائي إلى تيليجرام
sendTelegramMessage($chat_id, $reply, $telegram_token);


// =========================================================================
// الدوال المساعدة
// =========================================================================

/**
 * دالة لإرسال رسالة نصية إلى تيليجرام باستخدام cURL
 */
function sendTelegramMessage($chat_id, $text, $token) {
    $url = "https://api.telegram.org/bot" . $token . "/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    // تعيين timeout لتجنب تعليق السكربت
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
}

/**
 * دالة للتواصل مع نموذج Gemini 1.5 Flash باستخدام cURL
 */
function askGemini($prompt, $api_key) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $api_key;
    
    // إعداد هيكل الطلب المعتمد لـ Gemini 1.5 Flash
    $data = [
        "systemInstruction" => [
            "parts" => [
                ["text" => "أنت مساعد ذكي لمتجر ملابس حجاب وعبايات وفساتين اسمه 'The Queen'. أجب على الزبائن بطريقة ودية جداً، محترمة، ومهنية وبإيجاز. استخدم إيموجيز مناسبة. لا تقدم وعوداً بتوفر منتجات غير موجودة."]
            ]
        ],
        "contents" => [
            ["parts" => [["text" => $prompt]]]
        ],
        "generationConfig" => [
            "temperature" => 0.7,
            "maxOutputTokens" => 250
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $result = curl_exec($ch);
    
    if(curl_errno($ch)){
        curl_close($ch);
        return "عذراً، أواجه مشكلة في التفكير حالياً. يرجى المحاولة لاحقاً.";
    }
    
    curl_close($ch);
    
    $response = json_decode($result, true);
    
    if(isset($response['candidates'][0]['content']['parts'][0]['text'])){
        return trim($response['candidates'][0]['content']['parts'][0]['text']);
    }
    
    return "عذراً، لم أتمكن من فهم طلبك بشكل جيد. هل يمكنك توضيح ما تبحث عنه؟";
}
?>

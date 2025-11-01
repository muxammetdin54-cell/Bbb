<?php
// ============================
// Telegram Quiz Bot
// Bitta faylda ishlaydi
// ============================

// Bot tokeni
define('BOT_TOKEN', '8242689092:AAHSxfvzQw28cQMz-BrsuV4T1dkivWosYfU');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

// Standart savollar (agar questions.json bo'lmasa)
$defaultQuestions = [
    [
        "id" => 1,
        "question" => "PHP qaysi yilda yaratilgan?",
        "options" => ["1994", "1995", "1996", "1997"],
        "correct_answer" => 1,
        "explanation" => "PHP 1995 yilda Rasmus Lerdorf tomonidan yaratilgan."
    ],
    [
        "id" => 2,
        "question" => "Quyidagilardan qaysi biri PHP framework emas?",
        "options" => ["Laravel", "Django", "Symfony", "CodeIgniter"],
        "correct_answer" => 2,
        "explanation" => "Django - bu Python frameworki, PHP emas."
    ],
    [
        "id" => 3,
        "question" => "PHP fayllari qanday kengaytmaga ega?",
        "options" => [".php", ".html", ".js", ".py"],
        "correct_answer" => 1,
        "explanation" => "PHP fayllari .php kengaytmasiga ega."
    ],
    [
        "id" => 4,
        "question" => "String ma'lumot turi qanday belgilanadi?",
        "options" => ["Qo'shtirnoq", "Bittalik tirnoq", "Ikkala usulda ham", "Hech qanday"],
        "correct_answer" => 3,
        "explanation" => "String qo'shtirnoq (\") yoki bittalik tirnoq (') da yozilishi mumkin."
    ],
    [
        "id" => 5,
        "question" => "PHP da massiv qanday yaratiladi?",
        "options" => ["array()", "[]", "array[]", "Hammasi to'g'ri"],
        "correct_answer" => 4,
        "explanation" => "PHP da array() funktsiyasi yoki [] qavs yordamida massiv yaratish mumkin."
    ]
];

// Holatlar
define('STATE_IDLE', 0);
define('STATE_TAKING_QUIZ', 2);

// Fayl operatsiyalari
function saveToFile($filename, $data) {
    file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function readFromFile($filename) {
    if (!file_exists($filename)) {
        return null;
    }
    return json_decode(file_get_contents($filename), true);
}

// Foydalanuvchi ma'lumotlari
function getUserData($userId) {
    $userFile = "user_{$userId}.json";
    $data = readFromFile($userFile);
    
    if (!$data) {
        $data = [
            'user_id' => $userId,
            'state' => STATE_IDLE,
            'current_question' => 0,
            'score' => 0,
            'questions_answered' => 0,
            'quiz_questions' => []
        ];
        saveToFile($userFile, $data);
    }
    
    return $data;
}

function updateUserData($userId, $data) {
    $userFile = "user_{$userId}.json";
    saveToFile($userFile, $data);
}

// Savollarni o'qish
function getQuestions() {
    global $defaultQuestions;
    
    if (file_exists('questions.json')) {
        $data = readFromFile('questions.json');
        return $data ? $data['questions'] : $defaultQuestions;
    }
    
    // Agar questions.json yo'q bo'lsa, standart savollarni yaratish
    saveToFile('questions.json', ['questions' => $defaultQuestions]);
    return $defaultQuestions;
}

// Savollarni aralashtirish
function shuffleQuestions($questions, $count = 5) {
    shuffle($questions);
    return array_slice($questions, 0, $count);
}

// Telegram API
function sendTelegramRequest($method, $parameters) {
    $url = API_URL . $method;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

function sendMessage($chatId, $text, $replyMarkup = null) {
    $parameters = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($replyMarkup) {
        $parameters['reply_markup'] = json_encode($replyMarkup);
    }
    
    return sendTelegramRequest('sendMessage', $parameters);
}

// Inline keyboard
function createOptionsKeyboard($options) {
    $keyboard = [];
    
    foreach ($options as $index => $option) {
        $keyboard[] = [
            [
                'text' => $option,
                'callback_data' => 'answer_' . $index
            ]
        ];
    }
    
    $keyboard[] = [
        [
            'text' => '🚪 Quizni tugatish',
            'callback_data' => 'end_quiz'
        ]
    ];
    
    return ['inline_keyboard' => $keyboard];
}

// Quiz boshlash
function startNewQuiz($chatId, $userId) {
    $questions = getQuestions();
    
    if (empty($questions)) {
        sendMessage($chatId, "❌ Savollar topilmadi.");
        return;
    }
    
    $quizQuestions = shuffleQuestions($questions, 5);
    $userData = getUserData($userId);
    
    $userData['state'] = STATE_TAKING_QUIZ;
    $userData['current_question'] = 0;
    $userData['score'] = 0;
    $userData['questions_answered'] = 0;
    $userData['quiz_questions'] = $quizQuestions;
    
    updateUserData($userId, $userData);
    
    sendMessage($chatId, "🎯 <b>Quiz boshlandi!</b>\n\nSizga 5 ta savol beriladi.\nHar bir to'g'ri javob uchun 1 ball.\n\nBirinchi savol:");
    sendNextQuestion($chatId, $userId);
}

// Keyingi savol
function sendNextQuestion($chatId, $userId) {
    $userData = getUserData($userId);
    $currentIndex = $userData['current_question'];
    $questions = $userData['quiz_questions'];
    
    if ($currentIndex >= count($questions)) {
        finishQuiz($chatId, $userId);
        return;
    }
    
    $question = $questions[$currentIndex];
    $questionText = "<b>Savol " . ($currentIndex + 1) . ":</b>\n" . $question['question'];
    
    $options = $question['options'];
    $correctIndex = $question['correct_answer'];
    
    $userData['current_options'] = $options;
    $userData['current_correct'] = $correctIndex;
    updateUserData($userId, $userData);
    
    $keyboard = createOptionsKeyboard($options);
    sendMessage($chatId, $questionText, $keyboard);
}

// Quiz tugatish
function finishQuiz($chatId, $userId) {
    $userData = getUserData($userId);
    $score = $userData['score'];
    $total = count($userData['quiz_questions']);
    $percentage = round(($score / $total) * 100);
    
    $message = "🏁 <b>Quiz tugadi!</b>\n\n";
    $message .= "📊 Sizning natijangiz: {$score}/{$total} ({$percentage}%)\n\n";
    
    if ($percentage >= 80) {
        $message .= "🎉 A'lo natija! Juda yaxshi!";
    } elseif ($percentage >= 60) {
        $message .= "👍 Yaxshi natija!";
    } elseif ($percentage >= 40) {
        $message .= "😊 Qoniqarli natija";
    } else {
        $message .= "📚 Yana bir bor urinib ko'ring!";
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [
                [
                    'text' => '🔄 Yangi Quiz',
                    'callback_data' => 'start_quiz'
                ]
            ]
        ]
    ];
    
    sendMessage($chatId, $message, $keyboard);
    
    $userData['state'] = STATE_IDLE;
    updateUserData($userId, $userData);
}

// Asosiy kod
$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    // Agar webhook so'rov bo'lmasa, info chiqaramiz
    if ($_GET['test'] ?? false) {
        echo "🤖 Quiz Bot ishga tushdi!\n";
        echo "📞 Bog'lanish: https://t.me/" . ($_GET['bot'] ?? 'your_bot');
        echo "\n\nSavollar soni: " . count(getQuestions());
    }
    exit;
}

$message = $update['message'] ?? null;
$callbackQuery = $update['callback_query'] ?? null;

if ($message) {
    $chatId = $message['chat']['id'];
    $userId = $message['from']['id'];
    $text = $message['text'] ?? '';
    
    if ($text === '/start' || $text === '/start@' . ($_GET['bot'] ?? 'quiz_bot_bot')) {
        $welcomeMessage = "👋 <b>Quiz Botga xush kelibsiz!</b>\n\n";
        $welcomeMessage .= "Bu bot sizga turli mavzularda test savollarini beradi.\n\n";
        $welcomeMessage .= "📋 <b>Buyruqlar:</b>\n";
        $welcomeMessage .= "/start - Botni ishga tushirish\n";
        $welcomeMessage .= "/quiz - Yangi test boshlash\n";
        $welcomeMessage .= "/help - Yordam\n";
        $welcomeMessage .= "/add_question - Yangi savol qo'shish (admin)";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '🎯 Quizni boshlash',
                        'callback_data' => 'start_quiz'
                    ]
                ]
            ]
        ];
        
        sendMessage($chatId, $welcomeMessage, $keyboard);
        
    } elseif ($text === '/quiz') {
        startNewQuiz($chatId, $userId);
        
    } elseif ($text === '/help') {
        $helpMessage = "ℹ️ <b>Yordam</b>\n\n";
        $helpMessage .= "Bot quyidagi funksiyalarni bajaradi:\n";
        $helpMessage .= "• Savollarni tasodifiy tanlash\n";
        $helpMessage .= "• Variantlarni aralashtirish\n";
        $helpMessage .= "• Natijalarni hisoblash\n\n";
        $helpMessage .= "Quizni boshlash uchun /quiz buyrug'ini yuboring yoki 'Quizni boshlash' tugmasini bosing.";
        sendMessage($chatId, $helpMessage);
        
    } elseif (strpos($text, '/add_question') === 0 && $userId == 8242689092) { // Admin ID sini o'zgartiring
        // Yangi savol qo'shish (admin uchun)
        $parts = explode('|', $text);
        if (count($parts) >= 4) {
            $newQuestion = [
                'id' => time(),
                'question' => trim($parts[1]),
                'options' => array_map('trim', explode(',', $parts[2])),
                'correct_answer' => intval(trim($parts[3])),
                'explanation' => $parts[4] ?? "To'g'ri javob."
            ];
            
            $questions = getQuestions();
            $questions[] = $newQuestion;
            
            saveToFile('questions.json', ['questions' => $questions]);
            sendMessage($chatId, "✅ Yangi savol qo'shildi! Jami savollar: " . count($questions));
        } else {
            $help = "❌ Noto'g'ri format. To'g'ri format:\n";
            $help .= "<code>/add_question|Savol matni?|Variant1,Variant2,Variant3,Variant4|To'gri javob indeksi|Tushuntirish</code>\n\n";
            $help .= "Misol:\n";
            $help .= "<code>/add_question|PHP da qanday izoh yoziladi?|// izoh,/* izoh */,# izoh,Hammasi|3|PHP da hamma usulda izoh yozish mumkin</code>";
            sendMessage($chatId, $help);
        }
    }
}

if ($callbackQuery) {
    $chatId = $callbackQuery['message']['chat']['id'];
    $userId = $callbackQuery['from']['id'];
    $data = $callbackQuery['data'];
    
    if ($data === 'start_quiz') {
        startNewQuiz($chatId, $userId);
    } elseif ($data === 'end_quiz') {
        finishQuiz($chatId, $userId);
    } elseif (strpos($data, 'answer_') === 0) {
        $userData = getUserData($userId);
        
        if ($userData['state'] !== STATE_TAKING_QUIZ) {
            sendMessage($chatId, "❌ Avval quizni boshlashingiz kerak!");
            return;
        }
        
        $selectedIndex = intval(str_replace('answer_', '', $data));
        $correctIndex = $userData['current_correct'];
        $currentQuestion = $userData['quiz_questions'][$userData['current_question']];
        
        $message = "";
        if ($selectedIndex === $correctIndex) {
            $message .= "✅ <b>To'g'ri javob!</b>\n";
            $userData['score']++;
        } else {
            $message .= "❌ <b>Noto'g'ri javob!</b>\n";
            $message .= "To'g'ri javob: <b>" . $currentQuestion['options'][$correctIndex] . "</b>\n";
        }
        
        $message .= "\n💡 <i>" . $currentQuestion['explanation'] . "</i>";
        
        sendMessage($chatId, $message);
        
        // Keyingi savolga o'tish
        $userData['current_question']++;
        $userData['questions_answered']++;
        updateUserData($userId, $userData);
        
        // Keyingi savolni yuborish
        sleep(1);
        sendNextQuestion($chatId, $userId);
    }
}
?>

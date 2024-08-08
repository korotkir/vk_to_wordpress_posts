<?php
require_once('/var/www/html/wp-load.php');

// Подключение файлов для взаимодействия с медиафайлами и проч.
if ( ! function_exists( 'media_handle_sideload' ) ) {
  require_once( '/var/www/html/wp-admin/includes/media.php' );
  require_once( '/var/www/html/wp-admin/includes/file.php' );
  require_once( '/var/www/html/wp-admin/includes/image.php' );
}

// Строка для подтверждения адреса сервера из настроек Callback API 
$confirmation_token = ''; 

// Ключ доступа сообщества 
$token = ''; 

// Получаем и декодируем уведомление 
$data = json_decode(file_get_contents('php://input')); 

$fp = fopen("hellobotlog.txt", "a"); // Создаем/Открываем файл с логом

$img_tmp_dir = '/var/www/html/vk_actions/temp/'; // Путь до папки temp

function upload_image_from_url($image_url) {
    // Проверка, существует ли изображение по указанному URL
    if (empty($image_url)) {
        fwrite($fp, "[" . date('Y-m-d H:i:s') . "] URL недоступен.\n");
        return;
    }

    // Получение данных изображения
    $image_data = file_get_contents($image_url);
    if (!$image_data) {
        fwrite($fp, "[" . date('Y-m-d H:i:s') . "] Не удалось получить изображение.\n");
        return;
    }

    // Генерация уникального имени для файла
    $filename = uniqid('image_', true) . '.jpg';

    // Создание временного файла
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['path'] . '/' . $filename;

    // Запись данных в файл
    file_put_contents($file_path, $image_data);
    
    // Загрузка изображения в WordPress
    $attachment = array(
        'guid' => $upload_dir['url'] . '/' . basename($file_path),
        'post_mime_type' => wp_check_filetype($filename, null)['type'],
        'post_title' => sanitize_file_name($filename),
        'post_content' => '',
        'post_status' => 'inherit'
    );

    // Вставка информации об изображении в базу данных
    $attach_id = wp_insert_attachment($attachment, $file_path);
    
    // Обработка вложения для использования в медиабиблиотеке
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
    wp_update_attachment_metadata($attach_id, $attach_data);
    
    // Возвращаем ID загруженного изображения
    return $attach_id;
}


if (!isset($_REQUEST)) { 
  return; 
} 
fwrite($fp, "[" . date('Y-m-d H:i:s') . "] Request arrived\n");

// Проверяем, что находится в поле "type" 
switch ($data->type) {
    // Если это уведомление для подтверждения адреса... 
    case 'confirmation': 
        // ...отправляем строку для подтверждения 
        echo $confirmation_token; 
        break; 

    // Если это уведомление о новом посте...
    case 'wall_post_new':
        
        // ...получаем текст поста
        $all_content = $data->object->text;
        // Разделяем текст поста на строки
        $lines = explode("\n", $all_content);
        // Первая строка будет заголовком
        $post_title = isset($lines[0]) ? array_shift($lines) : '';
        // Оставшийся текст будет содержанием
        $post_content = implode("\n", $lines);
        $post_attachments = $data->object->attachments; // Является массивом, обходится foreach-ем
        
        // Возвращаем "ok" серверу Callback API 
        echo('ok'); 
        break; 
} 

fwrite($fp, "[" . date('Y-m-d H:i:s') . "] Request attachments reached \n");

//$urlsString = "";
$thumbnail_id = "";
foreach ($post_attachments as $k => $attachment) {
    fwrite($fp, "[" . date('Y-m-d H:i:s') . "] Обработка вложений \n");
    fwrite($fp, "[" . date('Y-m-d H:i:s') . "] Тип вложения: {$attachment->type} \n");

    if ($attachment->type == 'photo') {
        try {
            fwrite($fp, "[" . date('Y-m-d H:i:s') . "] Найдено изображение \n");
            //$urlsString .= "<a href=\"{$attachment->photo->sizes[count($attachment->photo->sizes) - 1]->url}\" target=\"_blank\"><img src=\"{$attachment->photo->sizes[3]->url}\" class=\"img-responsive img-thumbnail\"></a>\n";
            // $thumbnail_id = upload_image_from_url($attachment->photo->sizes[count($attachment->photo->sizes) - 1]->url);
            $image_from_vk = $attachment->photo->sizes[count($attachment->photo->sizes) - 1]->url; // 
            $image_data = file_get_contents($image_from_vk);

            if ($image_data === false) {
                fwrite($fp, "[" . date('Y-m-d H:i:s') . "] Произошла ошибка при загрузке изображения \n");
            }

            $temp_file_path = $img_tmp_dir . uniqid('image_', true) . '.jpg';
            $result = file_put_contents($temp_file_path, $image_data);

            if ($result === false) {
                fwrite($fp, "[" . date('Y-m-d H:i:s') . "] Произошла ошибка при сохранении изображения \n");
            }

            $thumbnail_id = upload_image_from_url($temp_file_path);

        } catch(Exception $e) {
            fwrite($fp, "[" . date('Y-m-d H:i:s') . "] $e->getMessage() \n");
        }
        
    }
}

$data = preg_replace('/(\[)(\w+)(\|)([\w ]+)(\])/u', "<a href=\"https://vk.com/$2\">$4</a>", $post_content);

$post_wordpress = '<p>' . $data . '</p><p>' . $urlsString . '</p>';
$new_post = array(
    'post_author' => 2,
    'post_title' => $post_title,
    'post_content' => $post_wordpress,
    'post_status' => 'publish',
    'comment_status' => 'closed'
);

define('WP_USE_THEMES', false);
require_once('../wp-load.php');
fwrite($fp, "[" . date('Y-m-d H:i:s') . "] New post reached \n");

// Устанавливаем обработчик ошибок
set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($fp) {
    fwrite($fp, "[" . date('Y-m-d H:i:s') . "] Ошибка: [$errno] $errstr в файле $errfile на строке $errline \n");
});

// Инициализируем обработку исключений
set_exception_handler(function ($exception) use ($fp) {
    fwrite($fp, "[" . date('Y-m-d H:i:s') . "] Исключение: " . $exception->getMessage() . "\n");
});

if ($post_content != '' || $urlsString != '') {
    $post_id = wp_insert_post($new_post);
    
    fwrite($fp, "[" . date('Y-m-d H:i:s') . "] post_id: $post_id + thumbnail_id: $thumbnail_id \n");
    
    // Замените $post_ID на $post_id
    $upload_image = set_post_thumbnail($post_id, $thumbnail_id);

    if ($upload_image === false) {
        fwrite($fp, "[" . date('Y-m-d H:i:s') . "] Изображение не установилось на обложку. Произошла ошибка. \n");
    } else {
        fwrite($fp, "[" . date('Y-m-d H:i:s') . "] Изображение успешно установилось на обложку записи\n");
    }
} else {
    fwrite($fp, "[" . date('Y-m-d H:i:s') . "] Post does not support \n");
}

// Очистим временный файл изображения
if (file_exists($temp_file_path)) {
    unlink($temp_file_path);
}

fclose($fp);
?>

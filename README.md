# vk_to_wordpress_posts
<span>PHP-скрипт для интеграции постов из сообщества VK в записи сайта на Wordpress<span>
<h3>Важно:</h3>
<ul>
  <li>Первая строка поста является заголовком записи</li>
  <li>Изображение загрузится не в img, а как миниатура поста (через медиафайлы)</li>
  <li>При каждом переходе во вкладку Callback API - строка подтверждения меняется</li>
  <li>Не забудьте сделать владельцем вашей папки и вложенных в нее файлов пользователя от которого работает wordpress (часто www-data)</li>
</ul>
<h3>Инструкция:</h3>
<ul>
<li> В корневой папке сайта (возм. /var/www/html) создаем папку с произвольным названием</li>
<li> Добавляем в эту папку файл index.html</li>
<li> Создаем папку temp (для временного хранения изображений поста, после выполнения скрипта они удаляются)</li>
<li> Не забываем указать строку подтверждения и ключ доступа (Ваше сообщество/Управление/Работа с API/Callback API)</li>
<li> В настройках Callback API в поле прав ставим чекбокс напротив "Добавление поста"</li>
</ul>

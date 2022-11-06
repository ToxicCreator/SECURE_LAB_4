
<?php
if (isset($_GET['Submit'])) {
  checkToken($_REQUEST['user_token'], $_SESSION['session_token']);

  $id = $_GET['id'];

  $db = new PDO('mysql:host=localhost;dbname=dvwa', $user, $password);

  if (is_numeric($id)) {
    $data = $db->prepare('SELECT first_name, last_name FROM users WHERE user_id = (:id) LIMIT 1;');
    $data->bindParam(':id', $id, PDO::PARAM_INT);
    $data->execute();

    if ($data->rowCount() == 1) {
      $html .= '<pre>User ID exists in the database.</pre>';
    } else {
      header($_SERVER[ 'SERVER_PROTOCOL' ].' 404 Not Found');
      $html .= '<pre>User ID is MISSING from the database.</pre>';
    }
  }
}

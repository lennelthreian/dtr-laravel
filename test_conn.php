<?php
try {
  $pdo = new PDO("mysql:host=192.168.1.151;port=3306;dbname=dtr_system", "root", "");
  echo "ok";
} catch (Exception $e) {
  echo $e->getMessage();
}


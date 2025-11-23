<?php
$input = 'Zetech123';
$hash = '$2y$10$1dV0k8Um9McF6YlDQ9S4nOeKp6bYpt5cFtaglRGnztS...'; // full hash
if (password_verify($input, $hash)) {
    echo "Password matches!";
} else {
    echo "Password DOES NOT match!";
}
?>

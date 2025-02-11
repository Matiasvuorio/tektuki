<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $message = htmlspecialchars($_POST['message']);

    $to = "matias@jyvaskylandigituki.fi";
    $subject = "Uusi yhteydenotto: $name";
    $body = "Nimi: $name\nSähköposti: $email\n\nViesti:\n$message";
    $headers = "From: $email";

    if (mail($to, $subject, $body, $headers)) {
        // Palataan index.html-sivulle ja lisätään URL-parametri onnistumisesta
        header("Location: index.html?status=success");
        exit();
    } else {
        // Palataan index.html-sivulle ja lisätään URL-parametri epäonnistumisesta
        header("Location: index.html?status=error");
        exit();
    }
}
?>
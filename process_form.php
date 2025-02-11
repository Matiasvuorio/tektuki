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
        echo "Viesti lähetetty onnistuneesti!";
    } else {
        echo "Viestin lähettäminen epäonnistui. Yritä myöhemmin uudelleen.";
    }
}
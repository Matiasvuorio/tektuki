<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $contact_method = htmlspecialchars($_POST['contact_method']);
    $message = htmlspecialchars($_POST['message']);
    
    $phone = isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : 'Ei ilmoitettu';

    $to = "matias@jyvaskylandigituki.fi";
    $subject = "Uusi yhteydenotto: $name";
    $body = "Nimi: $name\nSähköposti: $email\n\n";
    
    if ($contact_method === "phone") {
        $body .= "Haluaa yhteydenoton PUHELIMITSE\nPuhelinnumero: $phone\n\n";
    } else {
        $body .= "Haluaa yhteydenoton SÄHKÖPOSTILLA\n\n";
    }
    
    $body .= "Viesti:\n$message";
    $headers = "From: $email";

    if (mail($to, $subject, $body, $headers)) {
        header("Location: index.html?status=success");
        exit();
    } else {
        header("Location: index.html?status=error");
        exit();
    }
}
?>
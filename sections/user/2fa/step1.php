<?php

use Endroid\QrCode\QrCode;
use Endroid\QrCode\ErrorCorrectionLevel;

$qrCode = new QrCode('otpauth://totp/' . SITE_NAME . '?secret=' . $_SESSION['private_key']);
$qrCode->setSize(300);
$qrCode->setMargin(10);
$qrCode->setErrorCorrectionLevel(ErrorCorrectionLevel::HIGH());
$qrCode->setForegroundColor(['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0]);
$qrCode->setBackgroundColor(['r' => 255, 'g' => 255, 'b' => 255, 'a' => 0]);


View::show_header('Two-factor Authentication');
?>

<div class="box pad">
    <p>Please note that if you lose your 2FA key and all of your backup keys, the <?= SITE_NAME ?> staff cannot help you retrieve your account. Ensure you keep your backup keys in a safe place.</p>

    <p>We've generated a secure secret that only you and me should know. Please import it into your phone, either by
        scanning the QR key, or copying in the small text below that. We recommend using the Authy app which you can get
        from the <a href="https://itunes.apple.com/gb/app/authy/id494168017?mt=8">App Store</a> or <a
                href="https://play.google.com/store/apps/details?id=com.authy.authy&hl=en_GB">Play Store</a>. You can
        use the <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2&hl=en_GB">Google
            Authenticator</a> app, however. Tap the next button once you've done that.</p>
</div>

<div class="box box2">
    <div class="center pad">
        <div>
            <img src="<?=$qrCode->writeDataUri();?>">
            <div class="twofa_text">Secret Text: <span><?=$_SESSION['private_key']?></span></div>

            <?php if(isset($_GET['invalid'])): ?>
                <p class="warning">Please ensure you've imported the correct key into your authentication app and try again.</p>
            <?php endif; ?>
        </div>

        <a href="user.php?action=2fa&do=enable2&userid=<?= G::$LoggedUser['ID'] ?>" id="pad_next">Next &raquo;</a>
    </div>
</div>

<?php View::show_footer(); ?>

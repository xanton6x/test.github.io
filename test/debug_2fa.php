<?php
// שנה את זה למפתח הסודי שמופיע לך ב-DB או ב-Register
$test_secret = "הכנס_כאן_את_המפתח_הסודי_שלך"; 

include 'db.php'; // כדי להשתמש בפונקציות ה-base32 שכתבנו

function get_current_otp($secret) {
    $t = floor(time() / 30);
    $sha1 = hash_hmac('sha1', pack('N*', 0, $t), base32_decode($secret), true);
    $offset = ord($sha1[19]) & 0xf;
    $hash = (((ord($sha1[$offset+0]) & 0x7f) << 24) | ((ord($sha1[$offset+1]) & 0xff) << 16) | ((ord($sha1[$offset+2]) & 0xff) << 8) | (ord($sha1[$offset+3]) & 0xff)) % 1000000;
    return str_pad($hash, 6, '0', STR_PAD_LEFT);
}

echo "<h2>בדיקת סנכרון 2FA</h2>";
echo "זמן שרת נוכחי: " . date("H:i:s") . "<br>";
echo "קוד ה-OTP שהשרת מצפה לו כרגע: <b style='font-size:24px; color:blue;'>" . get_current_otp($test_secret) . "</b><br>";
echo "<p>אם הקוד הזה שונה ממה שמופיע לך בטלפון, יש בעיית סנכרון זמן.</p>";
?>
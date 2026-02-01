<?php
// אבטחה בסיסית - ניתן להוסיף כאן בדיקת Session אם תרצה
$conn = new mysqli("localhost", "root", "", "it_vault");
$conn->set_charset("utf8mb4");

$result = $conn->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 200");
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>מערכת לוגים - IT Vault</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; padding: 30px; }
        .log-container { max-width: 1100px; margin: auto; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h2 { color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #2c3e50; color: white; padding: 12px; text-align: right; }
        td { padding: 10px; border-bottom: 1px solid #eee; font-size: 14px; }
        tr:hover { background: #f9f9f9; }
        .tag { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .tag-login { background: #d4edda; color: #155724; }
        .tag-save { background: #cce5ff; color: #004085; }
        .tag-danger { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="log-container">
        <h2>📋 יומן פעולות מערכת (Audit Log)</h2>
        <table>
            <thead>
                <tr>
                    <th>זמן</th>
                    <th>משתמש</th>
                    <th>פעולה</th>
                    <th>פרטי שינוי / לקוח</th>
                    <th>כתובת IP</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): 
                    $class = "";
                    if(strpos($row['action'], 'Login') !== false) $class = "tag-login";
                    if(strpos($row['action'], 'Data') !== false) $class = "tag-save";
                    if(strpos($row['action'], 'Deleted') !== false || strpos($row['details'], 'Deleted') !== false) $class = "tag-danger";
                ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                    <td><strong><?= htmlspecialchars($row['username']) ?></strong></td>
                    <td><span class="tag <?= $class ?>"><?= htmlspecialchars($row['action']) ?></span></td>
                    <td><?= htmlspecialchars($row['details']) ?></td>
                    <td style="color: #999; font-family: monospace;"><?= $row['ip_address'] ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
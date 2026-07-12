<?php
require_once 'config/db.php';

// First, check what's in the admins table
$result = $conn->query("SELECT username, password FROM admins WHERE username = 'admin'");
if ($row = $result->fetch_assoc()) {
    echo "Current password hash: " . $row['password'] . "<br><br>";
    
    // Test if current hash works
    if (password_verify('admin123', $row['password'])) {
        echo "✅ Current password works with 'admin123'<br>";
    } else {
        echo "❌ Current password does NOT work with 'admin123'<br><br>";
        echo "Updating password...<br>";
        
        // Update with correct hash
        $new_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE username = 'admin'");
        $stmt->bind_param("s", $new_hash);
        
        if ($stmt->execute()) {
            echo "✅ Password updated successfully!<br>";
            echo "New hash: " . $new_hash . "<br>";
            
            // Verify new hash
            if (password_verify('admin123', $new_hash)) {
                echo "✅ New password verification successful!<br>";
                echo "<br><strong>You can now login with:</strong><br>";
                echo "Username: admin<br>";
                echo "Password: admin123";
            }
        } else {
            echo "❌ Update failed: " . $stmt->error;
        }
        $stmt->close();
    }
} else {
    echo "Admin user not found! Creating...<br>";
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO admins (username, password) VALUES ('admin', ?)");
    $stmt->bind_param("s", $hash);
    if ($stmt->execute()) {
        echo "✅ Admin created successfully!<br>";
        echo "Username: admin<br>";
        echo "Password: admin123";
    }
}

$conn->close();
?>
<html>
    <head>
        <title>Login</title>
      <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css"
      />
    </head>
    <body>
        <?php
        $db = new PDO('sqlite:db.sqlite');
        if (!isset($session['username']) and !isset($session['password'])){
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $username = $_POST["username"];
                $password = $_POST["password"];
                $stmt = $db->prepare('SELECT * FROM users WHERE username = :username');
                $stmt->bindParam(':username', $username, PDO::PARAM_STR);
                $stmt->execute();
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (password_verify($password, $row['password'])) {
                        echo "Login successful!";
                        session_start();
                        $_SESSION['username'] = $username;
                        $_SESSION['password'] = $password;
                        header('Location: index.php');
                    } else {
                        echo "Invalid password.";
                    }
                } else {
                    echo "Invalid username.";
                }
            }
        }
        ?>


        <div class="container" style="margin-top: 8%;">
          <h1>Login</h1>
          <hr />
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="post">
          <label>Username<input type="text" id="username" name="username" required /></label>
          <label>Username<input type="password" id="password" name="password" required /></label>
          <button type="submit" class="btn">Login</button>
        </form>
        </div>
    </body>
</html>
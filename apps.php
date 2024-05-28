<?php
session_start();
$db = new PDO('sqlite:db.sqlite');
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    // last request was more than 30 minutes ago
    session_unset();     // unset $_SESSION variable for the run-time
    session_destroy();   // destroy session data in storage
}
$_SESSION['LAST_ACTIVITY'] = time(); // update last activity time stamp
$username = $_SESSION['username'];
$password = $_SESSION['password'];
if (isset($_SESSION['username']) and isset($_SESSION['password'])) {
    $stmt = $db->prepare('SELECT * FROM users WHERE username = :username');
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (password_verify($password, $row['password'])) {
            $auth = true;
        } else {
            $auth = false;
        }
    } else {
        $auth = false;
    }
} else {
    $auth = false;
}
?>
<html>
<head>
  <title>Apps</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css"/>
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.colors.min.css"
  />
</head>
<?php if ($auth) : ?>
  <body>
  <div class="container" style="margin-top: 6%">
    <header>
      <section>
        <h1>Apps</h1>
        <div class="grid">
          <div role="button" tabindex="0" class="secondary" onclick="location.href='index.php';">Back</div>
          <div role="button" tabindex="0" class="pico-background-azure-100" onclick="location.href='apps/edit.php';">Edit</div>
          <div role="button" tabindex="0" class="pico-background-red-500" onclick="location.href='apps/delete.php';">Delete</div>
          <div role="button" tabindex="0" class="pico-background-jade-150" onclick="location.href='apps/new.php';">Create</div>
        </div>
      </section>
    </header>
    <hr />
    <main>
      <section>
        <div class="overflow-auto">
          <table>
            <thead>
            <tr>
              <th scope="col">Name</th>
              <th scope="col">Image</th>
              <th scope="col">Version</th>
              <th scope="col">Status</th>
              <th scope="col">Link</th>
              <th scope="col">Comments</th>
            </tr>
            </thead>
            <tbody>
            <?php
            // Connect to the SQLite database
            $db = new PDO('sqlite:db.sqlite');

            // SQL statement to select all records from the apps table
            $selectQuery = "SELECT * FROM apps";

            // Execute the query
            $result = $db->query($selectQuery);

            // Fetch all records
            $apps = $result->fetchAll(PDO::FETCH_ASSOC);

            // Loop through the fetched records and display each record in a table row
            foreach ($apps as $app) {
                if ($app['Status'] == 'running') {
                   $colour = 'pico-background-jade-150';
                } elseif ($app['Status'] == 'down') {
                  $colour = 'pico-background-red-500';
                } else {
                    $colour = 'pico-background-amber-200';
                }
                echo "<tr>";
                echo "<td>" . htmlspecialchars($app['Name']) . "</td>";
                echo "<td>" . htmlspecialchars($app['Image']) . "</td>";
                echo "<td>" . htmlspecialchars($app['Version']) . "</td>";
                echo "<td class='". $colour . "'>" . htmlspecialchars($app['Status']) . "</td>";
                echo "<td><a href='http://" . htmlspecialchars($app['Url']) . "'>" . htmlspecialchars($app['Url']) . "</a></td>";
                echo "<td>" . htmlspecialchars($app['Comment']) . "</td>";
                echo "</tr>";
            }
            ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
    <footer>
      <hr />
      <section>
        <p>&copy; 2021 Apps</p>
      </section>
    </footer>
  </div>
  </body>
<?php endif; ?>
</html>

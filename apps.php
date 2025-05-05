<?php
require_once 'includes/auth.php'; // Use centralized auth
require_once 'includes/functions.php'; // Include functions for admin check
require_admin(); 
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
        <?php 
          $error_message = null;
          $apps = [];
          try {
              // Connect to the SQLite database - $db is now available from auth.php
              // SQL statement to select all records from the apps table
              $selectQuery = "SELECT ID, ContainerName, Image, Comment, Url FROM apps"; // Use ContainerName
              // Execute the query
              $result = $db->query($selectQuery);
              // Fetch all records
              $apps = $result->fetchAll(PDO::FETCH_ASSOC);
          } catch (PDOException $e) {
              // Log error: error_log("Apps Page DB Error: " . $e->getMessage());
              $error_message = "Failed to retrieve app data due to a database error.";
          }
        ?>
        <?php if ($error_message): ?>
            <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
        <?php else: ?>
            <div class="overflow-auto">
              <table>
                <thead>
                <tr>
                  <th scope="col">Container Name</th> <!-- Changed header -->
                  <th scope="col">Image</th>
                  <th scope="col">Version</th>
                  <th scope="col">Status</th>
                  <th scope="col">Link</th>
                  <th scope="col">Comments</th>
                </tr>
                </thead>
                <tbody>
                <?php
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
                    echo "<td>" . htmlspecialchars($app['ContainerName']) . "</td>"; // Use ContainerName
                    echo "<td>" . htmlspecialchars($app['Image']) . "</td>";
                    echo "<td>" . htmlspecialchars($app['Version'] ?? 'N/A') . "</td>"; // Handle potential missing Version
                    echo "<td class='". $colour . "'>" . htmlspecialchars($app['Status'] ?? 'Unknown') . "</td>"; // Handle potential missing Status
                    echo "<td><a href='http://" . htmlspecialchars($app['Url']) . "'>" . htmlspecialchars($app['Url']) . "</a></td>";
                    echo "<td>" . htmlspecialchars($app['Comment']) . "</td>";
                    echo "</tr>";
                }
                ?>
                </tbody>
              </table>
            </div>
        <?php endif; ?>
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
</html>

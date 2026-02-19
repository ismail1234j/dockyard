<?php
require_once 'includes/auth.php';

$isAdmin = isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true;
$user_id = $_SESSION['user_id'] ?? null;

?>
<!DOCTYPE html>
<html data-theme="light">
<head>
    <title>Containers</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Pico CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.orange.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.colors.min.css" />
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />

    <style>
        article {
            max-width: 1400px;
            margin: 0 auto;
            box-shadow: var(--pico-card-sectioning-background-color);
        }
        
        .header-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--pico-spacing);
        }

        .status-running { color: #388e3c; font-weight: bold; }
        .status-exited, .status-stopped { color: #d32f2f; font-weight: bold; }
        .status-created, .status-unknown { color: #f57c00; font-weight: bold; }
        
        .action-buttons { 
            display: flex; 
            gap: 8px; 
            justify-content: flex-end;
        }

        .action-button { 
            cursor: pointer; 
            padding: 0.4rem 0.8rem;
            border-radius: var(--pico-border-radius);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            border: none;
            transition: opacity 0.2s ease;
        }

        .action-button:hover { opacity: 0.85; }
        .action-button i { margin-right: 5px; }

        .action-start { background-color: #50C878; color: white; }
        .action-stop { background-color: #FF6B6B; color: white; }
        .action-logs { background-color: #5DADE2; color: white; }

        /* Modal Overrides for Pico v2 */
        .view-logs-modal { 
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            background-color: rgba(0,0,0,0.75); 
            backdrop-filter: blur(4px);
        }

        .modal-content { 
            background-color: #13171f; 
            margin: 2% auto; 
            padding: 24px; 
            width: 95%; 
            max-width: 1000px; 
            max-height: 90vh; 
            overflow-y: auto; 
            border-radius: 12px;
            border: 1px solid #30363d;
        }

        .container-logs { 
            background-color: #0d1117; 
            padding: 20px; 
            border-radius: 8px; 
            font-family: 'Fira Code', 'Courier New', monospace; 
            white-space: pre-wrap; 
            max-height: 60vh; 
            overflow-y: auto; 
            color: #d1d5db; 
            font-size: 0.9rem;
            line-height: 1.5;
            border: 1px solid #30363d;
        }

        .close-button { 
            color: #9ca3af; 
            float: right; 
            font-size: 1.5rem; 
            font-weight: bold; 
            cursor: pointer; 
            line-height: 1;
        }
        
        .close-button:hover { color: white; }

        .table-header { 
            display: flex; 
            justify-content: space-between;
            align-items: center; 
            margin-bottom: 1.5rem; 
        }

        .refresh-table {
            width: auto;
            margin: 0;
            padding: 0.5rem 1rem;
        }

        footer p {
            font-size: 0.8rem;
            color: var(--pico-muted-color);
            margin: 0;
        }
    </style>
</head>
<body>
    <script src="https://cdn.jsdelivr.net/npm/htmx.org@2.0.8/dist/htmx.min.js"></script>
    <div class="container" style="padding-top: 4rem; padding-bottom: 4rem;">
        <article>
            <header>
                <div class="header-nav">
                    <hgroup style="margin: 0;">
                        <h1>Containers</h1>
                    </hgroup>
                    <button class="secondary outline" onclick="location.href='index.php';" style="width: auto;">
                        <i class="fa fa-arrow-left"></i> Back
                    </button>
                </div>
            </header>

            <main>
                <section>
                    <div class="table-header">
                        <h2 style="font-size: 1.25rem; margin: 0;"><i class="fa fa-cubes"></i> Active Containers</h2>
                        <button class="refresh-table secondary"
                                hx-get="/apps/action.php?action=table&name=dummy"
                                hx-target="#container-table-body"
                                hx-swap="innerHTML">
                            <i class="fa fa-refresh"></i> Refresh Table
                        </button>
                    </div>
                    
                    <div id="container-table-container" class="overflow-auto">
                        <table class="striped">
                            <thead>
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Image</th>
                                    <th scope="col">Version</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Port</th>
                                    <th scope="col" style="text-align: right;">Management</th>
                                </tr>
                            </thead>
                            <tbody id="container-table-body"
                                hx-get="/apps/action.php?action=table&name=dummy"
                                hx-trigger="load"
                                hx-swap="innerHTML">
                                <!-- Data populated automatically by HTMX on page load -->
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>

            <footer>
                <hr />
            </footer>
        </article>
    </div>
    
    <script>
        // Use the CSRF token from PHP
        window.csrfToken = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
    </script>
</body>
</html>

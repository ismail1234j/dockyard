function refreshTable() {
  const tableBody = document.getElementById('container-table-body');
  const originalContent = tableBody.innerHTML;

  fetch('apps/container_list.php')
  .then(response => {
    if (!response.ok) {
      throw new Error(`HTTP error! Status: ${response.status}`);
    }
    return response.text();
  })

  .then(data => {
    if (data && data.trim().length > 0) {
      tableBody.innerHTML = data;
    } else {
      console.error('Empty response received from container_list.php');
      tableBody.innerHTML = '<tr><td colspan="7">No containers found or there was an issue loading the list. Check error logs.</td></tr>';
    }
  })
  .catch(error => {
    console.error('Refresh error:', error);
    tableBody.innerHTML = originalContent;
    alert(`Error refreshing container list: ${error.message}. Check server logs for details.`);
  });
}

async function fetchLogs($containerName) {
    try {
        const response = await fetch('fetch_logs.php?name=' + $containerName); // Endpoint to fetch logs
        if (response.ok) {
            const logs = await response.text();
            document.getElementById('logs').textContent = logs;
        } else {
            document.getElementById('logs').textContent = 'Failed to fetch logs.';
        }
    } catch (error) {
        document.getElementById('logs').textContent = 'Error fetching logs.';
    }
}

function startContainer(name) {
  if (confirm("Are you sure you want to start the container " + name + "?")) {
      window.location.href = "action.php?start=" + encodeURIComponent(name);
  }
}

function stopContainer(name) {
  if (confirm("Are you sure you want to stop the container " + name + "?")) {
      window.location.href = "action.php?stop=" + encodeURIComponent(name);
  }
}

async function fetchPlayers() {
    try {
        const res = await fetch('fetch_players.php');
        document.getElementById('players').textContent = res.ok
            ? await res.text()
            : 'Failed to fetch players.';
    } catch {
        document.getElementById('players').textContent = 'Error fetching players.';
    }
}
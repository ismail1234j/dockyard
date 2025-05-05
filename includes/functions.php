<?php

/// Function to display a modal for any argument passed to it
function Modal($title, $message) {
        echo <<<HTML
        <!-- Modal HTML structure -->
        <div id="modal" class="modal" style="display:block; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.4);">
            <div style="background-color:#13171f; margin:15% auto; padding:20px; border:1px solid #888; width:80%; max-width:500px; border-radius:8px; box-shadow:0 4px 8px rgba(0,0,0,0.2);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                    <h3 style="margin:0;">$title</h3>
                    <span id="close-modal" style="cursor:pointer; font-size:24px; font-weight:bold;">&times;</span>
                </div>
                <hr style="margin:0 0 15px 0; border:0; border-top:1px solid #eee;">
                <p>$message</p>
                <div style="text-align:right; margin-top:20px;">
                    <button id="dismiss-modal" class="secondary">Dismiss</button>
                </div>
            </div>
        </div>

        <!-- Modal JavaScript -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Get the modal
                var modal = document.getElementById('modal');
                
                // Get the close button element
                var closeBtn = document.getElementById('close-modal');
                
                // Get the dismiss button
                var dismissBtn = document.getElementById('dismiss-modal');
                
                // Function to close the modal
                function closeModal() {
                    modal.style.display = "none";
                    // Remove the error parameter from the URL without refreshing the page
                    const url = new URL(window.location);
                    url.searchParams.delete('action');
                    window.history.replaceState({}, document.title, url);
                }
                
                // When the user clicks on the close button, close the modal
                closeBtn.onclick = closeModal;
                
                // When the user clicks on the dismiss button, close the modal
                dismissBtn.onclick = closeModal;
                
                // When the user clicks anywhere outside of the modal, close it
                window.onclick = function(event) {
                    if (event.target == modal) {
                        closeModal();
                    }
                }
            });
        </script>
HTML;
}

?>
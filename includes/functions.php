<?php

/// Function to display a modal for any argument passed to it
function Modal($title, $message) {
        echo <<<HTML
        <dialog open id='errorDialog'>
            <article>
                <header>
                <button aria-label='Close' rel='prev' id='closeErrorDialog'></button>
                <p>
                    <strong>$title</strong>
                </p>
                </header>
                <p>
                <strong>$message</strong>
            </article>
        </dialog>
        <script>
          const errorDialog = document.getElementById('errorDialog');
          const closeButton = document.getElementById('closeErrorDialog');
          
          if (closeButton && errorDialog) {
            closeButton.addEventListener('click', () => {
              errorDialog.close();
            });
          }
        </script>
HTML;
}

?>
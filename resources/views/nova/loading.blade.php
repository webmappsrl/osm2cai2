<!DOCTYPE html>
<html>

<head>
    <title>Download in corso...</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f9f9f9;
        }

        .loading-container {
            text-align: center;
            padding: 20px;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <div class="loading-container">
        <div class="spinner"></div>
        <h2>Download in corso...</h2>
        <p>La finestra si chiuderà automaticamente al termine del download.</p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            var downloadUrl = '/api/{{ $type }}/{{ $model }}/{{ $id }}';
            var link = document.createElement('a');
            link.href = downloadUrl;
            document.body.appendChild(link);
            link.click();

            setTimeout(function() {
                window.close();
            }, 5000); // wait 5 seconds before closing
        });
    </script>
</body>

</html>

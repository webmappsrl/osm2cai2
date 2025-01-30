<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Imported UGCs</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }

        .container {
            width: 80%;
            margin: auto;
            overflow: hidden;
        }

        header {
            background: #50b3a2;
            color: white;
            padding-top: 30px;
            min-height: 70px;
            border-bottom: #e8491d 3px solid;
        }

        header a {
            color: #ffffff;
            text-decoration: none;
            text-transform: uppercase;
            font-size: 16px;
        }

        header li {
            float: left;
            display: inline;
            padding: 0 20px 0 20px;
        }

        header #branding {
            float: left;
        }

        header #branding h1 {
            margin: 0;
        }

        header nav {
            float: right;
            margin-top: 10px;
        }

        header .highlight,
        header .current a {
            color: #e8491d;
            font-weight: bold;
        }

        header a:hover {
            color: #cccccc;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Import Finished</h1>
        @if ($poi < 1 && $track < 1 && $media < 1 && count($updatedElements) < 1)
            <p>No elements were created or updated.</p>
        @else
            <p>Poi created: {{ $poi }}</p>
            <p>Track created: {{ $track }}</p>
            <p>Media created: {{ $media }}</p>
            <p>The following elements were updated:</p>
            <ul>
                @foreach ($updatedElements as $element)
                    <li>{{ $element }}</li>
                @endforeach
            </ul>
        @endif
        <a href="javascript:window.close();">Close this page</a>
    </div>
</body>

</html>

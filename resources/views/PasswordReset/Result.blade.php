<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>@if($success) Password Changed @else Error @endif</title>
    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        h3 {
            margin: 0 0 20px;
        }
    </style>
</head>
<body>
@if($success)
<div style="margin: 0 20px 0 20px">
    <div class="alert alert-success" style="text-align: center; font-size: 15px; padding: 20px;">
        <h2>Successful!</h2>
        <p style="margin-bottom: 20px;">
            Your password was changed successfully!
        </p>
        <button class="btn btn-primary" onclick="window.location = '{{ env('FRONT_END_URL') }}';">Login</button>
    </div>
</div>
@else
<div style="margin: 0 20px 0 20px">
    <div class="alert alert-danger" style="text-align: center; font-size: 15px; padding: 20px;">
        <h2>Error!</h2>
        <p style="margin-bottom: 20px;">
            Failed to change the password! Something went wrong! Please contact the administration!
        </p>
        <button class="btn btn-primary" onclick="window.location = '{{ env('FRONT_END_URL') }}';">Go Home</button>
    </div>
</div>
@endif

<!-- jQuery library -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
<!-- Latest compiled JavaScript -->
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>
</html>

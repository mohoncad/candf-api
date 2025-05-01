<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Verify Email</title>
    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
</head>
<body style="height: 100vh; display: flex; justify-content: center; align-items: center;">
@if(isset($verification_result))
    @if($verification_result == "__success__")
        <div style="padding: 10px;">
            <div class="alert alert-success" style="text-align: center; font-size: 15px;">
                <h2>Successful!</h2>
                <p style="margin-bottom: 20px;">
                    We have successfully verified your email address!
                </p>
            </div>
        </div>
    @endif
    @if($verification_result == "__already_verified__")
        <div style="padding: 10px;">
            <div class="alert alert-info" style="text-align: center; font-size: 15px;">
                <h2>Already Verified!</h2>
                <p style="margin-bottom: 20px;">
                    Your email address was verified before!
                </p>
            </div>
        </div>
    @endif
    @if($verification_result == "__error__")
        <div style="padding: 10px;">
            <div class="alert alert-danger" style="text-align: center; font-size: 15px;">
                <h2>Something Went Wrong!</h2>
                <p style="margin-bottom: 20px;">
                    The link you requested is not available or moved permanently!
                </p>
            </div>
        </div>
    @endif
@endif
<!-- jQuery library -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
<!-- Latest compiled JavaScript -->
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>
</html>

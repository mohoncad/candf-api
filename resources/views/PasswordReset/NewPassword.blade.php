<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>New Password</title>
    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .NewPassWordContainer {
            border: 1px solid #a6a6a6;
            padding: 20px;
            border-radius: 5px;
            width: 300px;
        }
        h3 {
            margin: 0;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="NewPassWordContainer">
    <h3>
        New Password
    </h3>
    <form name="PasswordChangeForm" action="/recover/password/new/{{$PasswordResetToken}}" method="POST"
          onsubmit="return ValidatePasswordChangeForm();">
        <input type="hidden" value="{{csrf_token()}}" name="_token"/>
        <div class="form-group">
            <label for="NewPassword">New Password:</label>
            <input type="password" class="form-control" id="NewPassword" name="NewPassword" />
        </div>
        <div class="form-group">
            <label for="ConfirmPassword">Confirm Password:</label>
            <input type="password" class="form-control" id="ConfirmPassword" name="ConfirmPassword"/>
        </div>
        <button type="submit" class="btn btn-default">Change</button>
    </form>
</div>
<!-- jQuery library -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
<!-- Latest compiled JavaScript -->
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
<Script>
    /**
     * @return {boolean}
     */
    const ValidatePasswordChangeForm = function () {
        let NewPassword = document.forms['PasswordChangeForm']['NewPassword'];
        let ConfirmPassword = document.forms['PasswordChangeForm']['ConfirmPassword'];
        if (NewPassword.value === "") {
            $('#NewPassword').css({"border": "1px solid red"});
            NewPassword.focus();
            return false;
        } else {
            $('#NewPassword').css({"border": "1px solid green"});
        }
        if (ConfirmPassword.value === "") {
            $('#ConfirmPassword').css({"border": "1px solid red"});
            ConfirmPassword.focus();
            return false;
        } else {
            $('#ConfirmPassword').css({"border": "1px solid green"});
        }
        if (NewPassword.value !== ConfirmPassword.value) {
            $('#ConfirmPassword').css({"border": "1px solid red"});
            ConfirmPassword.focus();
            return false;
        } else {
            $('#ConfirmPassword').css({"border": "1px solid green"});
        }
        return true;
    };
</Script>
</body>
</html>

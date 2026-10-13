<?php
session_start();

    include("connection.php");
    include("functions.php");

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    $user_name = $_POST['user_name'];
    $password = $_POST['password'];

    if (!empty($user_name) && !empty($password))
    {

        $query = "select * from users where user_name = '$user_name' limit 1";
        $result = mysqli_query($con, $query);

        if ($result && mysqli_num_rows($result) > 0)
        {
            $user_data = mysqli_fetch_assoc($result);

            if (password_verify($password, $user_data['password']))
            {
                $_SESSION['user_id'] = $user_data['user_id'];
                $_SESSION['user_type'] = $user_data['user_type'];

                if ($user_data['user_type'] == "Admin")
                {
                    header("Location: admin/admin_dashboard.php");
                }
                else
                {
                    header("Location: client/client_dashboard.php");
                }

                die;
            }
        }

        echo "Wrong username or password!";
    }
    else
    {
        echo "Please enter your username and password!";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kvffie Arts | Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@1,900&family=Montserrat:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <main>
        <h1>Login</h1>

        <form method="post">
        <input type="text" name="user_name" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>

        <button type="submit">Login</button>

        <p>
            Don't have an account?
            <a href="signup.php">Sign Up</a>
        </p>
        </form>
    </main>
</body>
</html>
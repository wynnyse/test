<?php
session_start();

include("connection.php");
include("functions.php");

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    $user_name = $_POST['user_name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $security_question = $_POST['security_question'];
    $security_answer = $_POST['security_answer'];

    if (!empty($user_name) && !empty($email) && !empty($password) && !empty($security_question) && !empty($security_answer))
    {
        // Check if username already exists
        $query = "SELECT * FROM users WHERE user_name = '$user_name' LIMIT 1";

        $result = mysqli_query($con, $query);

        if (mysqli_num_rows($result) > 0)
        {
            echo "Username already exists!";
        }
        else
        {
            // Check if email already exists
            $query = "SELECT * FROM users
                      WHERE email = '$email'
                      LIMIT 1";

            $result = mysqli_query($con, $query);

            if (mysqli_num_rows($result) > 0)
            {
                echo "Email already exists!";
            }
            else
            {
                // Create a unique user ID
                $user_id = random_num(20);

                // Check if the generated user ID already exists
                $query = "SELECT * FROM users WHERE user_id = '$user_id' LIMIT 1";

                $result = mysqli_query($con, $query);

                while (mysqli_num_rows($result) > 0)
                {
                    $user_id = random_num(20);

                    $query = "SELECT * FROM users WHERE user_id = '$user_id' LIMIT 1";

                    $result = mysqli_query($con, $query);
                }

                // Encrypt password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // New accounts are Clients
                $user_type = "Client";

                // Insert new Client account
                $query = "INSERT INTO users (user_id, user_name, email, password, security_question, security_answer, user_type) VALUES ('$user_id', '$user_name', '$email', '$hashed_password', '$security_question', '$security_answer', '$user_type')";

                if (mysqli_query($con, $query))
                {
                    header("Location: login.php");
                    die;
                }
                else
                {
                    echo "Error creating account!";
                }
            }
        }
    }
    else
    {
        echo "Please fill in all fields!";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kvffie | Get Started</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@1,900&family=Montserrat:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/signup.css">
</head>
<body>
    <main>

        <h1>Create Account</h1>

        <form method="post">

            <label for="user_name">Username</label>
            <input type="text" id="user_name" name="user_name" required>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <label for="security_question">Security Question</label>
            <select id="security_question" name="security_question" required>

                <option value="">Select a question</option>
                <option value="What is your favorite color?">
                    What is your favorite color?
                </option>
                <option value="What is your favorite food?">
                    What is your favorite food?
                </option>
                <option value="What is your favorite animal?">
                    What is your favorite animal?
                </option>
                <option value="What is your childhood nickname?">
                    What is your childhood nickname?
                </option>
            
            </select>

            <label for="security_answer">Security Answer</label>
            <input type="text" id="security_answer" name="security_answer" required>

            <button type="submit">Sign Up</button>

            <p>
                Already have an account?
                <a href="login.php">Login</a>
            </p>

        </form>

    </main>
</body>
</html>
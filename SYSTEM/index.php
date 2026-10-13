<?php

//THIS LANDING PAGE OKEH 
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kvffie Arts | Welcome!</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@1,900&family=Montserrat:wght@500;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="css/landingpage.css">
</head>

<body>

    <!-- Header -->
    <header class="site-header">
        <h1 class="logo">Kvffie Arts</h1>

        <nav>
            <a class="nav-link" href="#home">Home</a>
            <a class="nav-link" href="#features">Features</a>
            <a class="btn-outline" href="signup.php">Create an Account</a>
            <a class="btn-solid" href="login.php">Log In</a>
        </nav>
    </header>

    <main>

        <!-- Hero Section -->
        <section class="hero" id="home">

            <div class="hero__content">
                <h2>Custom Digital Art Made for You</h2>

                <p>
                    Bringing your ideas to life through personalized
                    digital artwork.
                </p>

                <a class="hero__cta" href="login.php">Request a Commission</a>
            </div>

            <img
                class="hero__art"
                src="imgs/mascot.png"
                alt="Illustrated character wearing a checkered cloth"
            >

        </section>

        <!-- Features Section -->
        <section class="features" id="features">

            <h2 class="features__title">Services</h2>

            <p class="features__subtitle">
                Everything you need for your commission.
                Explore services, request artwork, and track your project.
            </p>

            <div class="features__grid">

                <article class="card">
                    <h3>Custom Illustrations</h3>
                    <p>
                        Submit your custom art ideas and provide the details
                        needed to bring your vision to life.
                    </p>
                </article>

                <article class="card">
                    <h3>Creative Services</h3>
                    <p>
                        Explore available digital art services and find
                        the right option for your creative needs.
                    </p>
                </article>

                <article class="card">
                    <h3>Project Tracking</h3>
                    <p>
                        Keep track of your commission progress, revisions,
                        deadlines, and completed artwork.
                    </p>
                </article>

            </div>

        </section>

    </main>

    <!-- that one js year code -->
    <footer class="site-footer">
        <p>Kvffie Arts.</p>
    </footer>

</body>
</html>

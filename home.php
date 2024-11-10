<?php
session_start();

include("php/config.php");
if (!isset($_SESSION['valid'])) {
    header("Location: index.php");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/style.css">
    <script src="scripts.js" defer></script>
    <title>Home</title>
</head>

<body>
    <div class="nav">
        <div class="logo">
            <p><a href="home.php">Logo</a> </p>
        </div>

        <div class="right-links">

            <?php

            $id = $_SESSION['id'];
            $query = mysqli_query($con, "SELECT*FROM users WHERE Id=$id");

            while ($result = mysqli_fetch_assoc($query)) {
                $res_Uname = $result['Username'];
                $res_Email = $result['Email'];
                $res_Age = $result['Age'];
                $res_id = $result['Id'];
            }

            echo "<a href='edit.php?Id=$res_id'>Change Profile Information</a>";
            ?>

            <a href="php/logout.php"> <button class="btn">Log Out</button> </a>

        </div>
    </div>

    <main>
        <!-- Welcome Message-->
        <div class="main-box top">
            <div class="top">
                <div class="box">
                    <p>Hello <b><?php echo $res_Uname ?></b>, Welcome</p>
                </div>
                <div class="box">
                    <p>Your email is <b><?php echo $res_Email ?></b>.</p>
                </div>
            </div>
            <!--  <div class="bottom">
                <div class="box">
                    <p>And you are <b><?php echo $res_Age ?> years old</b>.</p>
                </div>
            </div>-->
        </div>
    </main>
    <!-- CRUD for Discussions Begin -->


    <body>
        <div class="container-discussion">
            <?php

            $conn = include("php/config.php");
            if (isset($_POST['Add-discussion'])) {
                $title = $_POST['discussion-title'];
                $author = $res_Uname;
                $content = $_POST['discussion-content'];

                mysqli_query($con, "INSERT INTO discussion(title,author,content) VALUES('$title','$author','$content')") or die("Error Occured");

                echo "<div class='message'>
                <p>Discussion added to forum successfully!</p> </div> <br>";
            }

            ?>

            <header>Create New Discussions</header>

            <form action="" method="post">

                <div class="discussion input">
                    <label for="title">Discussion Title</label>
                    <input type="text" name="discussion-title" id="discussion-title" autocomplete="off" required>
                </div>

                <p>Discussion Author is: <b><?php echo $res_Uname ?></b> </p>
                <!--
                <div class="discussion input">
                    <label for="email">Email</label>
                    <input type="text" name="email" id="email" autocomplete="off" required>
                </div>-->

                <div class="discussion input">
                    <label for="discussion-content">Discussion Content</label>
                    <input type="Text" name="discussion-content" id="discussion-content" autocomplete="off" required>
                </div>

                <div class="field">

                    <input type="submit" class="btn" name="Add-discussion" value="Add discussion" required>
                </div>
        </div>
    </body>

    <div class="view-clickable-title">
        <?php

        $id = $_SESSION['id'];
        $query = mysqli_query($con, "SELECT*FROM discussion");

        while ($result = mysqli_fetch_assoc($query)) {
            $res_Uname = $result['Username'];
            $res_Email = $result['Email'];
            $res_Age = $result['Age'];
            $res_id = $result['Id'];
        }

        echo "<a href='edit.php?Id=$res_id'>Change Profile Information</a>";
        ?>

    </div>
</body>

</html>
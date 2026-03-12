<?php
// login.php
session_start();
require_once 'core/config.php';
if ($_POST) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['barangay_id'] = $user['barangay_id'];
        
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Sablayan Risk Assessment</title>

  <!-- Bootstrap & Tailwind -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
  body {
    background: 
      linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(129, 199, 132, 0.7)), /* leaf-green overlay */
      url('bg1.jpg') no-repeat center center; /* background image */
    background-size: cover;
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Poppins', sans-serif;
    margin: 0;
  }

  .login-card {
    background: #ffffff; /* solid white form */
    border-radius: 20px;
    box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4); /* leaf-green shadow */
    padding: 2rem;
    width: 100%;
    max-width: 400px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }

  .login-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 35px rgba(76, 175, 80, 0.6); /* stronger shadow on hover */
  }

  .icon-input {
    position: relative;
  }

  .icon-input i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #4caf50; /* green icon */
  }

  .icon-input input {
    padding-left: 35px;
  }

  .btn-primary {
    background: linear-gradient(to right, #4caf50, #81c784);
    border: none;
    transition: all 0.3s ease;
  }

  .btn-primary:hover {
    background: linear-gradient(to right, #388e3c, #66bb6a);
    transform: scale(1.02);
  }

  .form-control:focus {
    border-color: #2e7d32;
    box-shadow: 0 0 0 0.2rem rgba(46, 125, 50, 0.25);
  }

  .alert-danger {
    background-color: #fdecea;
    border-color: #f5c6cb;
    color: #a94442;
  }

  .text-green-light {
    color: #4caf50;
  }

  .animate-bounce {
    color: #4caf50; /* green bounce icon */
  }

  .text-muted {
    color: #757575;
  }
</style>

</head>
<body>
  <div class="card login-card w-full max-w-md p-4 md:p-6 text-white">
    <div class="card-body" style="color: #262626">
      <div class="text-center mb-5">
      <center><img src="logo.png" style="width: 150px; margin-top: -10px;"></center>
        <h2 class="text-2xl font-semibold tracking-wide">SABLAYAN RISK ASSESSMENT SYSTEM</h2>
        <p class="text-sm">Login to access your dashboard</p>
                <i class="mt-3 fas fa-map-marked-alt fa-3x mb-3 text-green-600 animate-bounce"></i>
      </div>

      <?php if (isset($error)): ?>
        <div class="alert alert-danger text-center">
          <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
        </div>
      <?php endif; ?>

      <form method="POST" style="margin-top: -40px;">
        <div class="mb-4 icon-input">
          <i class="fas fa-user"></i>
          <input type="text" name="username" class="form-control rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-400" placeholder="Username" required>
        </div>

        <div class="mb-4 icon-input">
          <i class="fas fa-lock"></i>
          <input type="password" name="password" class="form-control rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-400" placeholder="Password" required>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 rounded-lg mt-3">
          <i class="fas fa-sign-in-alt me-2"></i>Login
        </button>
      </form>

      <!--<div class="text-center mt-4 text-sm">-->
      <!--  <p class="text-gray-300">Forgot your password? <a href="#" class="text-indigo-300 hover:underline">Reset here</a></p>-->
      <!--</div>-->
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

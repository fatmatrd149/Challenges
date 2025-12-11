<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduMind+ - Educational Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="shared-assets/css/global.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; min-height: 100vh; display: flex; flex-direction: column; }
        .hero { flex: 1; display: flex; align-items: center; justify-content: center; text-align: center; padding: 50px 20px; }
        .hero h1 { font-size: 3rem; margin-bottom: 20px; font-weight: bold; }
        .hero p { font-size: 1.2rem; margin-bottom: 30px; max-width: 600px; margin-left: auto; margin-right: auto; }
        .btn-custom { background: rgba(255, 255, 255, 0.2); border: 2px solid white; color: white; padding: 12px 30px; margin: 10px; border-radius: 25px; text-decoration: none; transition: all 0.3s; }
        .btn-custom:hover { background: white; color: #667eea; }
        .features { padding: 50px 20px; background: rgba(0, 0, 0, 0.1); text-align: center; }
        .features h2 { margin-bottom: 30px; }
        .feature-list { display: flex; justify-content: center; flex-wrap: wrap; }
        .feature-item { margin: 20px; max-width: 250px; }
        .footer { padding: 20px; text-align: center; background: rgba(0, 0, 0, 0.2); }
    </style>
</head>
<body>
    <div class="hero">
        <div>
            <h1>🎓 Welcome to EduMind+</h1>
            <p>Your comprehensive educational platform for students, teachers, and admins. Engage with courses, quizzes, challenges, and rewards in a gamified learning experience.</p>
            <a href="admin-back-office/index.php" class="btn-custom">Admin Portal</a>
            <a href="teacher-back-office/index.php" class="btn-custom">Teacher Portal</a>
            <a href="front-office/index.php" class="btn-custom">Student Portal</a>
        </div>
    </div>
    <div class="features">
        <h2>🚀 Platform Features</h2>
        <div class="feature-list">
            <div class="feature-item">
                <h4>📝 Interactive Quizzes</h4>
                <p>Test your knowledge with dynamic quizzes and instant feedback.</p>
            </div>
            <div class="feature-item">
                <h4>📚 Course Management</h4>
                <p>Access and manage courses tailored for effective learning.</p>
            </div>
            <div class="feature-item">
                <h4>🏆 Challenges & Rewards</h4>
                <p>Complete gamified challenges to earn points and redeem exciting rewards.</p>
            </div>
            <div class="feature-item">
                <h4>📱 Offline Support</h4>
                <p>Continue learning even without internet—data syncs automatically.</p>
            </div>
        </div>
    </div>
    <div class="footer">
        <p>&copy; 2023 EduMind+. Empowering education through technology.</p>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="shared-assets/js/database.js"></script>
</body>
</html>
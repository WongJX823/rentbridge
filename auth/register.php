<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Create your account';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · RentBridge</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body style="background: var(--rb-cream);">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">

            <div class="text-center mb-4">
                <a href="../index.php" class="text-decoration-none d-inline-flex align-items-center gap-2 mb-3" style="color:var(--rb-navy); font-family:'Fraunces',serif; font-size:1.6rem; font-weight:600;">
                    <span style="width:30px;height:30px;background:var(--rb-emerald);border-radius:8px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1rem;transform:rotate(-6deg);">R</span>
                    RentBridge
                </a>
            </div>

            <div class="bg-white rounded-4 p-4 p-md-5 border">
                <div class="text-center mb-4">
                    <h1 class="mb-2">I am a&hellip;</h1>
                    <p class="text-secondary">Pick the role that matches you. We'll set up the right account.</p>
                </div>

                <div class="row g-3 my-4">
                    <div class="col-md-4">
                        <a href="register_student.php" class="d-block bg-white rounded-3 border p-4 text-decoration-none text-dark h-100 role-pick">
                            <div class="rb-role-icon mb-3"><i class="bi bi-mortarboard"></i></div>
                            <h4 class="h5">Student</h4>
                            <p class="text-secondary small mb-0">Looking for off-campus housing as a UTeM student.</p>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="register_landlord.php" class="d-block bg-white rounded-3 border p-4 text-decoration-none text-dark h-100 role-pick">
                            <div class="rb-role-icon mb-3"><i class="bi bi-house-heart"></i></div>
                            <h4 class="h5">Landlord</h4>
                            <p class="text-secondary small mb-0">Property owner who wants to list rental units to students.</p>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="register_agent.php" class="d-block bg-white rounded-3 border p-4 text-decoration-none text-dark h-100 role-pick">
                            <div class="rb-role-icon mb-3"><i class="bi bi-person-badge"></i></div>
                            <h4 class="h5">Agent <small class="text-secondary">(UTeM staff)</small></h4>
                            <p class="text-secondary small mb-0">UTeM staff witnessing contracts and supporting students.</p>
                        </a>
                    </div>
                </div>

                <p class="text-center mb-0 text-secondary">
                    Already have an account?
                    <a href="login.php" class="fw-semibold">Log in</a>
                </p>
            </div>

            <p class="text-center mt-4 small">
                <a href="../index.php" class="text-secondary text-decoration-none">
                    <i class="bi bi-arrow-left"></i> Back to home
                </a>
            </p>
        </div>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</body>
</html>
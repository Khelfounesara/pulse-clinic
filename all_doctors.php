<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$host = '127.0.0.1';
$dbname = 'pulse_clinic';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fetch specialties for filter buttons
$specialty_query = "SELECT DISTINCT specialty FROM doctors WHERE specialty IS NOT NULL ORDER BY specialty";
$specialty_stmt = $pdo->query($specialty_query);
$specialties = $specialty_stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle specialty filter
$selected_specialty = isset($_GET['specialty']) ? $_GET['specialty'] : null;
$doctor_query = "
    SELECT u.full_name, d.doctor_id, d.specialty, d.availability_status, up.profile_image
    FROM doctors d
    JOIN users u ON d.doctor_id = u.user_id
    LEFT JOIN user_profiles up ON u.user_id = up.user_id
    WHERE d.availability_status = 1
";
if ($selected_specialty) {
    $doctor_query .= " AND d.specialty = :specialty";
}
$doctor_stmt = $pdo->prepare($doctor_query);
if ($selected_specialty) {
    $doctor_stmt->bindParam(':specialty', $selected_specialty, PDO::PARAM_STR);
}
$doctor_stmt->execute();
$doctors = $doctor_stmt->fetchAll();

// Fetch user profile image safely
$profile_image = isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image']) 
    ? $_SESSION['profile_image'] 
    : 'image/8200435763dd55fbba824456fe180d76.jpg';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Doctors - Pulse Clinic</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom Tailwind Config -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#1E3A8A', // Deep blue
                        'secondary': '#3B82F6', // Lighter blue
                        'accent': '#10B981', // Green
                        'neutral': '#F3F4F6', // Light gray
                    },
                    fontFamily: {
                        'sans': ['Inter', 'ui-sans-serif', 'system-ui'],
                    },
                },
            },
        };
    </script>
    <!-- Custom CSS -->
    <style>
        html {
            scroll-behavior: smooth;
        }

        .doctor-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            min-height: 300px;
            display: flex;
            flex-direction: column;
        }

        .doctor-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }

        .specialty-btn {
            transition: all 0.3s ease;
        }

        .specialty-btn:hover, .book-btn:hover {
            transform: translateY(-2px);
        }

        .specialty-btn:focus, .book-btn:focus {
            outline: 2px solid #3B82F6;
            outline-offset: 2px;
        }

        /* Responsive Hamburger Menu */
        .nav-menu {
            transition: all 0.3s ease;
        }

        @media (max-width: 768px) {
            .nav-menu {
                display: none;
                flex-direction: column;
                position: absolute;
                top: 80px;
                right: 0;
                background: white;
                width: 100%;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                padding: 1rem;
            }

            .nav-menu.active {
                display: flex;
            }

            .hamburger {
                display: block;
            }
        }

        .hamburger {
            display: none;
            cursor: pointer;
        }

        .hamburger span {
            display: block;
            width: 25px;
            height: 3px;
            background: #1E3A8A;
            margin: 5px 0;
            transition: all 0.3s ease;
        }

        .hamburger.active span:nth-child(1) {
            transform: rotate(45deg) translate(5px, 5px);
        }

        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }

        .hamburger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }
    </style>
</head>
<body class="bg-neutral min-h-screen">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <header class="flex items-center justify-between py-6 border-b border-gray-200">
            <a href="afterlogin.php" class="text-3xl font-bold text-primary hover:text-secondary transition-colors">
                <i class="fas fa-heartbeat mr-2"></i>Pulse Clinic
            </a>
            <div class="hamburger" onclick="toggleMenu()">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <nav class="nav-menu flex items-center gap-8">
                <a href="afterlogin.php" class="text-lg text-gray-700 hover:text-secondary transition-colors">Home</a>
                <a href="all_doctors.php" class="text-lg text-primary font-semibold relative">
                    All Doctors
                    <span class="absolute -bottom-1 left-0 w-full h-0.5 bg-primary"></span>
                </a>
                <a href="contact.php" class="text-lg text-gray-700 hover:text-secondary transition-colors">Contact</a>
                <a href="profile.php" class="flex items-center">
                    <img src="<?php echo htmlspecialchars($profile_image); ?>" 
                         alt="Profile" 
                         class="w-10 h-10 rounded-full border-2 border-secondary hover:scale-110 transition-transform object-cover">
                </a>
            </nav>
        </header>

        <!-- Main Content Section -->
        <main class="py-12">
            <h2 class="text-2xl font-semibold text-gray-800 mb-8">Our Specialist Doctors</h2>

            <!-- Specialty Filters -->
            <div class="flex flex-wrap gap-3 mb-12">
                <a href="all_doctors.php" 
                   class="specialty-btn px-5 py-2 rounded-full text-sm font-medium <?php echo !$selected_specialty ? 'bg-secondary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>"
                   aria-label="Show all doctors">
                    All
                </a>
                <?php foreach ($specialties as $specialty): ?>
                    <a href="all_doctors.php?specialty=<?php echo urlencode($specialty); ?>" 
                       class="specialty-btn px-5 py-2 rounded-full text-sm font-medium <?php echo $selected_specialty === $specialty ? 'bg-secondary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>"
                       aria-label="Filter by <?php echo htmlspecialchars($specialty); ?>">
                        <?php echo htmlspecialchars($specialty); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Doctor Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php if (empty($doctors)): ?>
                    <p class="col-span-full text-center text-gray-600 text-lg">No doctors available for the selected specialty.</p>
                <?php else: ?>
                    <?php foreach ($doctors as $doctor): ?>
                        <div class="doctor-card bg-white rounded-lg shadow-md overflow-hidden flex flex-col" role="article">
                            <div class="flex justify-center pt-6">
                                <img src="<?php echo htmlspecialchars($doctor['profile_image'] ?: 'image/doc2.png'); ?>" 
                                     alt="Dr. <?php echo htmlspecialchars($doctor['full_name']); ?>" 
                                     class="w-24 h-24 rounded-full border-4 border-neutral object-cover">
                            </div>
                            <div class="p-6 flex flex-col flex-grow">
                                <div class="flex items-center mb-3">
                                    <span class="w-3 h-3 rounded-full bg-accent mr-2"></span>
                                    <span class="text-sm font-medium text-accent">
                                        <?php echo $doctor['availability_status'] ? 'Available' : 'Unavailable'; ?>
                                    </span>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($doctor['full_name']); ?></h3>
                                <p class="text-sm text-gray-500 mb-4"><?php echo htmlspecialchars($doctor['specialty'] ?: 'General Practitioner'); ?></p>
                                <a href="book_appointment.php?doctor_id=<?php echo $doctor['doctor_id']; ?>" 
                                   class="book-btn mt-auto inline-block text-center px-4 py-2 bg-secondary text-white rounded-full hover:bg-primary transition-colors"
                                   aria-label="Book appointment with Dr. <?php echo htmlspecialchars($doctor['full_name']); ?>">
                                    Book Appointment
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- JavaScript -->
    <script>
        // Toggle mobile menu
        function toggleMenu() {
            const menu = document.querySelector('.nav-menu');
            const hamburger = document.querySelector('.hamburger');
            menu.classList.toggle('active');
            hamburger.classList.toggle('active');
        }

        // Keyboard accessibility for buttons
        document.querySelectorAll('.specialty-btn, .book-btn').forEach(button => {
            button.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    button.click();
                }
            });
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    </script>
</body>
</html>
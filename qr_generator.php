<?php
/**
 * TappTrak QR Code Generator
 * Generate QR codes for visitors and visitor logs
 */

require_once 'config.php';

// Require login
requireLogin();

// Get database instance
$db = Database::getInstance();

// Get current user info
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// Handle QR code generation requests
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'visitor_qr' && isset($_GET['visitor_id'])) {
        $visitor_id = (int)$_GET['visitor_id'];
        
        // Get visitor information
        $sql = "SELECT * FROM visitors WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $visitor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $visitor = $result->fetch_assoc();
        $stmt->close();
        
        if ($visitor) {
            // Generate QR code data
            $qr_data = json_encode([
                'type' => 'visitor',
                'id' => $visitor['id'],
                'name' => $visitor['full_name'],
                'phone' => $visitor['phone'],
                'timestamp' => time()
            ]);
            
            // Generate QR code using JavaScript (client-side)
            echo generateQRCodeHTML($qr_data, $visitor['full_name']);
            exit;
        }
    }
    
    elseif ($action === 'visitor_log_qr' && isset($_GET['log_id'])) {
        $log_id = (int)$_GET['log_id'];
        
        // Get visitor log information
        $sql = "SELECT 
                    vl.*,
                    v.full_name as visitor_name,
                    v.phone as visitor_phone,
                    f.flat_number,
                    g.full_name as guard_name
                FROM visitor_logs vl
                JOIN visitors v ON vl.visitor_id = v.id
                JOIN flats f ON vl.flat_id = f.id
                JOIN guards g ON vl.guard_id = g.id
                WHERE vl.id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $log_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $visitor_log = $result->fetch_assoc();
        $stmt->close();
        
        if ($visitor_log) {
            // Generate QR code data
            $qr_data = json_encode([
                'type' => 'visitor_log',
                'id' => $visitor_log['id'],
                'visitor_name' => $visitor_log['visitor_name'],
                'visitor_phone' => $visitor_log['visitor_phone'],
                'flat_number' => $visitor_log['flat_number'],
                'checkin_time' => $visitor_log['check_in_time'],
                'expected_duration' => $visitor_log['expected_duration'],
                'status' => $visitor_log['status'],
                'timestamp' => time()
            ]);
            
            // Generate QR code using JavaScript (client-side)
            echo generateQRCodeHTML($qr_data, $visitor_log['visitor_name'] . ' - ' . $visitor_log['flat_number']);
            exit;
        }
    }
}

// Function to generate QR code HTML
function generateQRCodeHTML($data, $title) {
    return "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>QR Code - " . htmlspecialchars($title) . "</title>
        <script src='https://cdn.tailwindcss.com/3.4.16'></script>
        <script src='https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js'></script>
        <style>
            body { font-family: system-ui, -apple-system, sans-serif; }
        </style>
    </head>
    <body class='bg-gray-50'>
        <div class='min-h-screen flex items-center justify-center p-4'>
            <div class='bg-white rounded-lg shadow-lg p-8 max-w-md w-full text-center'>
                <h1 class='text-2xl font-bold text-gray-900 mb-4'>" . htmlspecialchars($title) . "</h1>
                <div id='qrcode' class='mb-6 flex justify-center'></div>
                <p class='text-sm text-gray-600 mb-4'>Scan this QR code for visitor information</p>
                <div class='text-xs text-gray-500'>
                    <p>Generated: " . date('d M Y, h:i A') . "</p>
                    <p>TappTrak Security System</p>
                </div>
                <div class='mt-6'>
                    <button onclick='window.print()' class='bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 mr-2'>
                        Print QR Code
                    </button>
                    <button onclick='window.close()' class='bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600'>
                        Close
                    </button>
                </div>
            </div>
        </div>
        
        <script>
            // Generate QR code
            const qrData = " . json_encode($data) . ";
            
            QRCode.toCanvas(document.getElementById('qrcode'), JSON.stringify(qrData), {
                width: 256,
                height: 256,
                color: {
                    dark: '#000000',
                    light: '#FFFFFF'
                },
                errorCorrectionLevel: 'M'
            }, function (error) {
                if (error) {
                    console.error('QR Code generation error:', error);
                    document.getElementById('qrcode').innerHTML = '<p class=\"text-red-500\">Error generating QR code</p>';
                } else {
                    console.log('QR Code generated successfully');
                }
            });
            
            // Auto-print when opened
            window.addEventListener('load', function() {
                setTimeout(function() {
                    window.print();
                }, 1000);
            });
        </script>
        
        <style media='print'>
            body { background: white; }
            .bg-gray-50 { background: white; }
            button { display: none; }
        </style>
    </body>
    </html>";
}

// Get all visitors for QR code generation
$sql = "SELECT * FROM visitors ORDER BY created_at DESC";
$visitors_result = $db->query($sql);
$visitors = [];
if ($visitors_result) {
    while ($row = $visitors_result->fetch_assoc()) {
        $visitors[] = $row;
    }
}

// Get recent visitor logs for QR code generation
$sql = "SELECT 
            vl.*,
            v.full_name as visitor_name,
            f.flat_number
        FROM visitor_logs vl
        JOIN visitors v ON vl.visitor_id = v.id
        JOIN flats f ON vl.flat_id = f.id
        ORDER BY vl.check_in_time DESC
        LIMIT 20";

$logs_result = $db->query($sql);
$visitor_logs = [];
if ($logs_result) {
    while ($row = $logs_result->fetch_assoc()) {
        $visitor_logs[] = $row;
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    logActivity('logout', 'users', $user_id);
    session_unset();
    session_destroy();
    redirect('index.php?logout=1');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - QR Code Generator</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <style>
        :where([class^="ri-"])::before { content: "\f3c2"; }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4FD1C7',
                        secondary: '#81E6D9'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 font-sans">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-primary shadow-lg">
            <div class="p-6 border-b border-white/20">
                <h1 class="text-white text-xl font-bold"><?php echo SITE_NAME; ?></h1>
                <p class="text-white/80 text-sm mt-1"><?php echo ucfirst($user_role); ?> Panel</p>
            </div>
            <nav class="mt-6">
                <div class="px-4 space-y-2">
                    <a href="dashboard.php" class="flex items-center px-4 py-3 text-white/80 hover:bg-white/10 rounded-lg transition-colors">
                        <div class="w-5 h-5 flex items-center justify-center mr-3">
                            <i class="ri-dashboard-line"></i>
                        </div>
                        Dashboard
                    </a>
                    <a href="guards.php" class="flex items-center px-4 py-3 text-white/80 hover:bg-white/10 rounded-lg transition-colors">
                        <div class="w-5 h-5 flex items-center justify-center mr-3">
                            <i class="ri-shield-user-line"></i>
                        </div>
                        Guards
                    </a>
                    <a href="alerts.php" class="flex items-center px-4 py-3 text-white/80 hover:bg-white/10 rounded-lg transition-colors">
                        <div class="w-5 h-5 flex items-center justify-center mr-3">
                            <i class="ri-alarm-warning-line"></i>
                        </div>
                        Alerts
                    </a>
                    <a href="visitors.php" class="flex items-center px-4 py-3 text-white/80 hover:bg-white/10 rounded-lg transition-colors">
                        <div class="w-5 h-5 flex items-center justify-center mr-3">
                            <i class="ri-file-list-3-line"></i>
                        </div>
                        Visitor Logs
                    </a>
                    <a href="qr_generator.php" class="flex items-center px-4 py-3 text-white bg-white/20 rounded-lg">
                        <div class="w-5 h-5 flex items-center justify-center mr-3">
                            <i class="ri-qr-code-line"></i>
                        </div>
                        QR Generator
                    </a>
                    <?php if (isAdmin()): ?>
                    <a href="buildings_flats.php" class="flex items-center px-4 py-3 text-white/80 hover:bg-white/10 rounded-lg transition-colors">
                        <div class="w-5 h-5 flex items-center justify-center mr-3">
                            <i class="ri-building-line"></i>
                        </div>
                        Buildings & Flats
                    </a>
                    <a href="settings.php" class="flex items-center px-4 py-3 text-white/80 hover:bg-white/10 rounded-lg transition-colors">
                        <div class="w-5 h-5 flex items-center justify-center mr-3">
                            <i class="ri-settings-line"></i>
                        </div>
                        Settings
                    </a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-hidden">
            <!-- Header -->
            <header class="bg-white shadow-sm border-b">
                <div class="px-8 py-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">QR Code Generator</h1>
                            <p class="text-gray-600 mt-1">Generate QR codes for visitors and visitor logs</p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="relative">
                                <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center cursor-pointer" onclick="toggleUserMenu()">
                                    <i class="ri-user-line text-white"></i>
                                </div>
                                <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                                    <div class="px-4 py-2 text-sm text-gray-700 border-b">
                                        <div class="font-medium"><?php echo htmlspecialchars($user_name); ?></div>
                                        <div class="text-gray-500"><?php echo ucfirst($user_role); ?></div>
                                    </div>
                                    <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="ri-user-settings-line mr-2"></i>Profile
                                    </a>
                                    <a href="qr_generator.php?logout=1" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="ri-logout-box-line mr-2"></i>Logout
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="p-8 space-y-8">
                <!-- Visitors QR Codes -->
                <section class="bg-white rounded-xl shadow-sm border">
                    <div class="p-6 border-b">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Visitor QR Codes</h2>
                                <p class="text-gray-600 text-sm mt-1">Generate QR codes for individual visitors</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <?php if (empty($visitors)): ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="ri-user-line text-4xl mb-2"></i>
                            <p>No visitors found. Add visitors first to generate QR codes.</p>
                        </div>
                        <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($visitors as $visitor): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <h3 class="font-medium text-gray-900"><?php echo htmlspecialchars($visitor['full_name']); ?></h3>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($visitor['phone']); ?></p>
                                    </div>
                                    <div class="w-12 h-12 bg-primary rounded-lg flex items-center justify-center">
                                        <i class="ri-user-line text-white text-xl"></i>
                                    </div>
                                </div>
                                <button onclick="generateVisitorQR(<?php echo $visitor['id']; ?>, '<?php echo htmlspecialchars($visitor['full_name']); ?>')" 
                                        class="w-full bg-primary text-white py-2 px-4 rounded-lg hover:bg-primary/90 transition-colors">
                                    <i class="ri-qr-code-line mr-2"></i>Generate QR Code
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Visitor Logs QR Codes -->
                <section class="bg-white rounded-xl shadow-sm border">
                    <div class="p-6 border-b">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Visitor Log QR Codes</h2>
                                <p class="text-gray-600 text-sm mt-1">Generate QR codes for visitor check-in/check-out logs</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <?php if (empty($visitor_logs)): ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="ri-file-list-3-line text-4xl mb-2"></i>
                            <p>No visitor logs found. Check in visitors first to generate QR codes.</p>
                        </div>
                        <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Visitor</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Flat</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check-in Time</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($visitor_logs as $log): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center mr-3">
                                                    <i class="ri-user-line text-white text-sm"></i>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($log['visitor_name']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($log['flat_number']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo formatDateTime($log['check_in_time']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $status = $log['status'];
                                            $status_class = '';
                                            $status_text = '';
                                            
                                            switch ($status) {
                                                case 'inside':
                                                    $status_class = 'bg-blue-100 text-blue-800';
                                                    $status_text = 'Inside';
                                                    break;
                                                case 'exited':
                                                    $status_class = 'bg-green-100 text-green-800';
                                                    $status_text = 'Exited';
                                                    break;
                                                case 'overstayed':
                                                    $status_class = 'bg-red-100 text-red-800';
                                                    $status_text = 'Overstayed';
                                                    break;
                                            }
                                            ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick="generateLogQR(<?php echo $log['id']; ?>, '<?php echo htmlspecialchars($log['visitor_name'] . ' - ' . $log['flat_number']); ?>')" 
                                                    class="text-primary hover:text-primary/80">
                                                <i class="ri-qr-code-line"></i> Generate QR
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script>
        // Toggle user menu
        function toggleUserMenu() {
            const menu = document.getElementById('userMenu');
            menu.classList.toggle('hidden');
        }

        // Close user menu when clicking outside
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('userMenu');
            const button = event.target.closest('.cursor-pointer');
            if (!button && !menu.contains(event.target)) {
                menu.classList.add('hidden');
            }
        });

        // Generate visitor QR code
        function generateVisitorQR(visitorId, visitorName) {
            const url = `qr_generator.php?action=visitor_qr&visitor_id=${visitorId}`;
            window.open(url, '_blank', 'width=600,height=700');
        }

        // Generate visitor log QR code
        function generateLogQR(logId, logTitle) {
            const url = `qr_generator.php?action=visitor_log_qr&log_id=${logId}`;
            window.open(url, '_blank', 'width=600,height=700');
        }
    </script>
</body>
</html>

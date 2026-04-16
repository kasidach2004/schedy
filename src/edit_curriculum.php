<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

// ตรวจสอบว่ามี id ที่ส่งมาหรือไม่
if (!isset($_GET['id'])) {
    header("Location: curriculums.php");
    exit;
}

$curriculum_id = (int)$_GET['id'];

// ดึงข้อมูลหลักสูตรที่ต้องการแก้ไข
$sql = "SELECT * FROM curriculums WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$curriculum_id, $_SESSION['user_id']]);
$curriculum = $stmt->fetch(PDO::FETCH_ASSOC);

// ถ้าไม่พบข้อมูลหรือไม่ใช่หลักสูตรของผู้ใช้นี้
if (!$curriculum) {
    header("Location: curriculums.php");
    exit;
}

// แปลงปี ค.ศ. เป็น พ.ศ.
$academic_year_be = $curriculum['academic_year'] + 543;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขหลักสูตร | ระบบจัดการตารางสอน</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --primary-color: #0d6efd;
            --accent-color: #0dcaf0;
        }

        body { 
            background: radial-gradient(circle at top left, #1f3747, #0f2027);
            background-attachment: fixed;
            color: #f8f9fa; 
            min-height: 100vh;
            font-family: 'Kanit', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .main-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .card-header-custom {
            background: rgba(0, 0, 0, 0.2);
            padding: 20px 30px;
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-body-custom {
            padding: 40px 30px;
        }

        /* Input Styling */
        .form-label {
            font-weight: 500;
            color: #e0e0e0;
            margin-bottom: 8px;
        }

        .input-group-text {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            border-right: none;
            color: #ccc;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            color: white;
            padding: 12px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--accent-color);
            box-shadow: 0 0 15px rgba(13, 202, 240, 0.3);
            color: white;
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }

        /* Button Styling */
        .btn-back {
            color: #ddd;
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            padding: 8px 15px;
            transition: all 0.3s;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .btn-submit {
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
            border: none;
            color: white;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.4);
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(13, 110, 253, 0.6);
            color: white;
        }

        .page-title {
            margin: 0;
            font-size: 1.5rem;
            background: linear-gradient(to right, #fff, #aab);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

    </style>
</head>
<body>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                
                <div class="main-card">
                    <div class="card-header-custom">
                        <h2 class="page-title"><i class="fas fa-edit me-2"></i>แก้ไขหลักสูตร</h2>
                        <a href="curriculums.php" class="btn-back">
                            <i class="fas fa-arrow-left me-1"></i> ย้อนกลับ
                        </a>
                    </div>

                    <div class="card-body-custom">
                        <form action="edit_curriculum_process.php" method="POST">
                            <input type="hidden" name="curriculum_id" value="<?= $curriculum_id ?>">
                            
                            <div class="mb-4">
                                <label for="curriculumName" class="form-label">ชื่อหลักสูตร</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-book"></i></span>
                                    <input type="text" class="form-control" id="curriculumName" name="curriculum_name" 
                                           value="<?= htmlspecialchars($curriculum['curriculum_name']) ?>" 
                                           placeholder="เช่น หลักสูตรวิทยาการคอมพิวเตอร์ 2568" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="semester" class="form-label">ภาคเรียน</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="text" class="form-control" id="semester" name="semester" 
                                           value="<?= htmlspecialchars($curriculum['semester']) ?>" 
                                           placeholder="เช่น 1, 2 หรือ ฤดูร้อน" required>
                                </div>
                            </div>

                            <div class="mb-5">
                                <label for="academicYear" class="form-label">ปีการศึกษา (พ.ศ.)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-graduation-cap"></i></span>
                                    <input type="number" class="form-control" id="academicYear" name="academic_year" 
                                           value="<?= $academic_year_be ?>" min="2500" max="2599" required>
                                </div>
                                <div class="form-text text-white-50 mt-2">
                                    <i class="fas fa-info-circle me-1"></i> กรุณากรอกปี พ.ศ. (เช่น 2569) ระบบจะแปลงเป็น ค.ศ. อัตโนมัติ
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-submit">
                                    <i class="fas fa-save me-2"></i> บันทึกการแก้ไข
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
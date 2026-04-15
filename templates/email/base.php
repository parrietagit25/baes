<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($subject) ? htmlspecialchars($subject) : 'Notificación'; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #0f2f57;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background: #e8f1ff;
            color: #0b3a6f;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            margin: -30px -30px 20px -30px;
            text-align: center;
            border-bottom: 1px solid #cddff7;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 20px 0;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background-color: #e8f1ff;
            color: #0b3a6f;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            border: 1px solid #cddff7;
        }
        .button:hover {
            background-color: #d9e8ff;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #355b86;
            text-align: center;
        }
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-success {
            background-color: #28a745;
            color: #0b3a6f;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #333;
        }
        .badge-danger {
            background-color: #dc3545;
            color: #0b3a6f;
        }
        .badge-info {
            background-color: #17a2b8;
            color: #0b3a6f;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo htmlspecialchars($app_name ?? 'AutoMarket Seminuevos'); ?></h1>
        </div>
        <div class="content">
            <?php echo $content ?? ''; ?>
        </div>
        <div class="footer">
            <p><strong>Por favor responder a todos</strong></p>
            <p>&copy; <?php echo date('Y'); ?> AutoMarket Seminuevos. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>


-- =============================================================================
-- MOTUS_BAES: Crear tablas SIN relaciones, insertar datos, luego agregar FKs
-- Evita errores de orden al importar. Ejecutar en phpMyAdmin o: mysql < archivo
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET UNIQUE_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `motus_baes` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `motus_baes`;

-- -----------------------------------------------------------------------------
-- 1. BANCOS (sin dependencias)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `bancos`;
CREATE TABLE `bancos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `direccion` text COLLATE utf8mb4_unicode_ci,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sitio_web` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contacto_principal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono_contacto` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_contacto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `idx_nombre` (`nombre`),
  KEY `idx_codigo` (`codigo`),
  KEY `idx_activo` (`activo`),
  KEY `idx_fecha_creacion` (`fecha_creacion`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `bancos` VALUES (6,'BAC','BAG','','','','','','','','',1,'2026-03-09 13:41:25','2026-03-09 13:41:25'),(7,'BAC - LEASING','BAC - LEASING','','','','','','','','',1,'2026-03-09 14:00:24','2026-03-09 14:00:24'),(8,'MULTIBANK','MULTIBANK','','','','','','','','',1,'2026-03-09 14:04:34','2026-03-09 14:04:34'),(9,'BANCO DELTA','BANCO DELTA','','','','','','','','',1,'2026-03-09 14:05:16','2026-03-09 14:05:16'),(10,'BANCO GENERAL - LEASING','BANCO GENERAL - LEAS','','','','','','','','',1,'2026-03-09 14:05:38','2026-03-09 14:05:38'),(11,'BANISI','BANISI','','','','','','','','',1,'2026-03-09 14:05:57','2026-03-09 14:05:57'),(12,'BANISTMO','BANISTMO','Banistmo Panamá','Torre Banistmo, Panamá','','info@banistmo.com','','','','',1,'2026-03-09 14:06:39','2026-03-09 14:06:39'),(13,'GLOBAL BANK','GLOBAL BANK','Global Bank Corporation','Calle 50, Panamá','','','','','','',1,'2026-03-09 14:07:37','2026-03-09 14:07:37'),(14,'BANESCO','BANESCO','','','','','','','','',1,'2026-03-09 14:10:43','2026-03-09 14:10:43'),(15,'AFINITI FINANCIAL','AFINITI FINANCIAL','','','','','','','','',1,'2026-03-09 14:10:58','2026-03-09 14:10:58'),(16,'DAVIVINDA','DAVIVINDA','','','','','','','','',1,'2026-03-09 14:11:09','2026-03-09 14:11:09'),(17,'FINANCIERA PACIFICO INTERNACIONAL','FINANCIERA PACIFICO ','FINANCIERA PACIFICO INTERNACIONAL','','','','','','','',1,'2026-03-09 14:11:39','2026-03-09 14:11:39'),(18,'CORPORACION DE CREDITO','CORPORACION DE CREDI','','','','','','','','',1,'2026-03-09 14:11:52','2026-03-09 14:11:52'),(19,'MULTIFINANCIAMIENTOS','MULTIFINANCIAMIENTOS','','','','','','','','',1,'2026-03-09 14:12:04','2026-03-09 14:12:04'),(20,'ISTHMUS CAPITAL','ISTHMUS CAPITAL','','','','','','','','',1,'2026-03-09 14:12:18','2026-03-09 14:12:18'),(21,'FOSTRIAN APOYO FINANCIERO','FOSTRIAN APOYO FINAN','','','','','','','','',1,'2026-03-09 14:12:57','2026-03-09 14:12:57'),(22,'FINANCIERA LA BENDICION, S.A.','FINANCIERA LA BENDIC','','','','','','','','',1,'2026-03-09 14:13:12','2026-03-09 14:13:12'),(23,'BANCO GENERAL','BANCO GENERAL','Banco General de Panamá','Av. Balboa, Panamá','+507 227-5000','info@bgeneral.com','https://www.bgeneral.com','Juan Pérez','+507 227-5001','jperez@bgeneral.com',1,'2026-03-09 14:13:41','2026-03-09 14:13:41');

-- -----------------------------------------------------------------------------
-- 2. ROLES (sin dependencias)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` VALUES (1,'ROLE_ADMIN','Administrador del sistema con acceso completo',1,'2025-08-13 22:10:58'),(7,'ROLE_GESTOR','Gestor de crédito - puede crear y gestionar solicitudes',1,'2025-09-12 15:38:08'),(8,'ROLE_BANCO','Analista bancario - puede aprobar/rechazar solicitudes',1,'2025-09-12 15:38:08');

-- -----------------------------------------------------------------------------
-- 3. USUARIOS (depende de bancos)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pais` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cargo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `banco_id` int DEFAULT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_cobrador` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_vendedor` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `primer_acceso` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_banco` (`banco_id`)
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `usuarios` VALUES (1,'Administrador','Sistema','admin@sistema.com','$2y$10$NtEbfuJZktlHbn7qZukSIuJnxK5AZOdZghon8eGwWYcjwXwEdA8Pe','México','Administrador del Sistema',NULL,NULL,NULL,NULL,1,0,'2025-08-13 22:10:58','2025-08-13 22:16:06'),(22,'Dayana','Samaniego','dayana.samaniego@automarketpan.com','$2y$10$4a0eEoIVSMBDOm5/VTLUe.OcCbAhJsCK0tyBFtIa6aUOsSy0gwE.K','','',NULL,'',NULL,NULL,1,1,'2025-11-26 18:53:29','2025-11-26 18:53:29'),(23,'Denia','Moscoso','denia.moscoso@automarketpan.com','$2y$10$HIh6ndVZ4k6WXMuN/vZGQuiJ2uVtd7tt/t1Hu77n2s.sEYcs0KPUW','','',NULL,'',NULL,NULL,1,1,'2025-11-26 18:55:46','2025-11-26 18:55:46'),(24,'Jakeline','Carvajal','Jakeline.carvajal@automarketpan.com','$2y$10$irHIrwiS/ax1fOPxqgzjbOXHK9EPCReXAEtZEgHu2aJ9I50BJ7sFW','','',NULL,'',NULL,NULL,1,1,'2025-11-26 19:00:05','2026-03-09 13:45:15'),(25,'Itzel','Rodriguez','itzel.rodriguez@automarketpan.com','$2y$10$Q9547WAxMVU5Jtyho9SVgO06/zAuAKJPPjweFDQXbOjq9loAUTaGa','','',NULL,'',NULL,NULL,1,1,'2025-11-26 19:01:54','2026-03-09 13:45:03'),(26,'Luis Ricardo','Coindet Mark','luisricardo.coindet@pa.bac.net','$2y$10$ZlPj0M5uC96LoRtcrN0X5unJb.rP34YT1sUcOMIrCWA9IAh92m1Ji','Panama','asesor',6,'6151-8663',NULL,NULL,1,1,'2026-03-09 13:44:40','2026-03-09 13:44:40'),(27,'Marcos','Garvey','mgarveyvaldes@pa.bac.net','$2y$10$VDG/M4LwTxOPrB22OZ4qOuvQu.KTfN9GUP1DIlZA.mfeFaaNyP6ky','Panamá','',6,'6140-3104',NULL,NULL,1,1,'2026-03-09 13:46:23','2026-03-09 13:46:23'),(28,'Melanie Jihan','Rodriguez Cueto','melanie.rodriguezc@pa.bac.net','$2y$10$C.eLlmFWrae2Pr0b7f4dOO/snC1B64erNvEt6e438LLBXZyPQz8a.','','',6,'6004-7811',NULL,NULL,1,1,'2026-03-09 13:47:42','2026-03-09 13:48:15'),(29,'Nazareth Victoria','Villarreal O.','nazareth.villarreal@pa.bac.net','$2y$10$MpLArXx7L4Spe3KVpxikHe/i76edRAbpFkXtx2yJKEV.RqY6iex3G','Panamá','',6,'6030-6495',NULL,NULL,1,1,'2026-03-09 13:49:58','2026-03-09 13:49:58'),(30,'Liz Naneth','Quiroz Rodriguez','liz.quiroz@pa.bac.net','$2y$10$omDFKYWxdx9fkIr2r75Ha.deCcDk4g6pBYGNsCeL1dE6IZozEUDrO','','',6,'6140-3223',NULL,NULL,1,1,'2026-03-09 13:52:20','2026-03-09 13:52:20'),(31,'Lillian','Castillo Samudio','lcastillos@pa.bac.net','$2y$10$g0vtAeJqG8QgQ5l/nAtOuOlh9EZ6pMZ5diCdLXBkLoflq7hK4k7ZC','','',6,'6949-4712',NULL,NULL,1,1,'2026-03-09 13:54:28','2026-03-09 13:54:28'),(32,'Abelino','Quintero ','Aquinteromiranda@pa.bac.net','$2y$10$G8OXA0QCRrwaI4zjtkt52.q3LL8.yxlVae0GrvsJu0rMvPh31ihkS','','',6,'6151-8710',NULL,NULL,1,1,'2026-03-09 13:55:53','2026-03-09 13:55:53'),(33,'Jairo O','Ortega V','JortegaVega@pa.bac.net','$2y$10$5vrf103vN3XNBc63rB/TVeKCNWf2QpFuUnG40t2dxNvwBnqlT4p0G','','',6,'6151-8660',NULL,NULL,1,1,'2026-03-09 13:58:17','2026-03-09 13:58:17'),(34,'Ernesto','Quintero','EQuinteroQuintero@pa.bac.net','$2y$10$qxsBMg24a52GKpIIzXPfwOS.zbaHwCxUYpUl6Vmo37conu1PJEtjm','','',7,'6144-4349',NULL,NULL,1,1,'2026-03-09 14:01:21','2026-03-09 14:01:21'),(35,'Yanitzel','Abrego Mosquera','yanitzel.abrego@pa.bac.net','$2y$10$Ua/AHTW77dJ6EKIUVzEtzOI3eYuKdLoEFGU.SfDk5CPhWCZHlJrHe','Panamá','',7,'6151-8700',NULL,NULL,1,1,'2026-03-09 14:03:05','2026-03-09 14:03:05'),(36,'Alexis','Garcés C.','agarces@multibank.com.pa','$2y$10$N5r.h8e0m6MAGoTqYjjeG.5Q88KG851bsiXBcjV9NAI.zs5TLtAQ.','','',8,'6923-5799',NULL,NULL,1,1,'2026-03-09 14:16:19','2026-03-09 14:17:25'),(37,'Amalia','Concepción','Amalia.Concepcion@multibank.com.pa','$2y$10$D11RY62kXSWdvo/8UsV3IO9m/t.F9Pq59/yZRbF1GBrH2YNeSJrpa','','',8,'6672-5679',NULL,NULL,1,1,'2026-03-09 14:18:46','2026-03-09 14:18:46'),(38,'Pablo','Castillo','Pablo.Castillo@multibank.com.pa','$2y$10$goH2LyCcMqBGf1fjdrq86OwWHv5UfXIZmWaKEhuxhbrYno6Vz1fnm','','',8,'6824-0594',NULL,NULL,1,1,'2026-03-09 14:21:15','2026-03-09 14:21:15'),(39,'Miriam Ines','Aparicio Pinillo','mdecaballero@multibank.com.pa','$2y$10$zEgpdFW9jzAHxKhEo07BiOc0XqtaEeXV7JvI9FpTG.VE9Xa2YN.fC','','',8,'',NULL,NULL,1,1,'2026-03-09 14:23:37','2026-03-09 14:23:37'),(40,'Olga','Torres','otorres@multibank.com.pa','$2y$10$mIn8MTakjHIbpaPiZgRwueyANWOt91ycHGqUP7vNSr3iHEWYd9fgq','','',8,'6469-5681',NULL,NULL,1,1,'2026-03-09 14:24:28','2026-03-09 14:24:58'),(41,'Damaisy Y','Bailey','dbailey@bandelta.com','$2y$10$yqekv/UJGj9Q6eDkTnIvCOkdBlhsP62/k3ITgsjSIqGTQjPLl8gy.','','',9,'',NULL,NULL,1,1,'2026-03-09 14:26:32','2026-03-09 14:26:32'),(42,'Fernando','Santos','fsantos@bandelta.com','$2y$10$NmVStrveMji5Y3QgpPCPyOwb9Jsfc89f/dZlHxs.GfawhO8vPF7cG','','',9,'6056-0236',NULL,NULL,1,1,'2026-03-09 14:28:03','2026-03-09 14:28:03'),(43,'Ronald O','Yanguez','ryanguez@bandelta.com','$2y$10$JkdBYGczR.1/fjidigr5q.vdkatKWndiAxnwWaHvw.woTY0j9pE3e','','',9,'',NULL,NULL,1,1,'2026-03-09 14:30:14','2026-03-09 14:30:14'),(44,'Luis F.','Gonzlez','luisfgonzalez@bgeneral.com','$2y$10$6ScaZX9wnkYgbQOhLEzoW.gF12uRr7M/QR8FyTAeAumaOitUs76I6','','',23,'6687-1328',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 15:37:18'),(45,'Mariolys Marleiny','Reyes A.','mreyes@bgeneral.com','123456',NULL,NULL,23,'6342-2583',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:49:48'),(46,'Conys','Castillo','cocastillo@bgeneral.com','123456',NULL,NULL,23,'6691-8981',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:49:48'),(47,'Darcy N.','Candanedo Gonzlez','dcandanedo@bgeneral.com','123456',NULL,NULL,23,'6480-6723',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:49:48'),(48,'Anayansi','de Barranco','anbarranco@bgeneral.com','$2y$10$HgiDwrxAb6i1SUGeLQoZ8OYYMfvEdNFDBAbk4cHWMQEPAp7IG3syG','','',10,'6923-3988',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 15:39:42'),(49,'Esai','Cornejo','escornejo@bgeneral.com','123456',NULL,NULL,10,'6235-7295',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:49:48'),(50,'Miriam K.','Espinosa','miespinosa@banisipanama.com','$2y$10$nsScAwG3v.4AHC9Cgiv4WerID/n4Xr/sVOUzoQDVsSFnkHronXpgi','','',11,'6679-7257',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 15:41:07'),(51,'Zuleika Maria','Rios Rios','zuleika.rios@banistmo.com','$2y$10$gzFKdRjUfxJQsdYvEXO3turDLxkoEXC73HbbjdfKsy1yx4xpzmuGW','','',12,'6549-4752',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 15:42:00'),(52,'Cesar','Ortiz Fernandez','cesar.ortiz@banistmo.com','123456',NULL,NULL,12,'6968-3232',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:49:48'),(53,'Banistmo','Leasing','serv_leasing@banistmo.com','123456',NULL,NULL,12,NULL,NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:49:48'),(54,'Melissa Grettel','Rellan Corella','melissa.g.rellan@banistmo.com','123456',NULL,NULL,12,'69495701',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:49:48'),(55,'Jose Eliecer','Ortiz','Jose.Ortiz@GlobalBank.com.pa','$2y$10$ZsvDCEWi0MFAV.e69JoZJuIjo1YiQCJW/yVUmfe3gAXCQRy4DozaW','','',13,'6379-7749',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 15:42:45'),(56,'Yira','Atencio','Yira.Atencio@GlobalBank.com.pa','123456',NULL,NULL,13,'6781-4836',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:49:48'),(57,'Yury','Dacosta','ydacosta@banesco.com','123456',NULL,NULL,14,NULL,NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:49:48'),(58,'Geraldine','Ortiz','gortiz@banesco.com','123456',NULL,NULL,14,'6136-9301',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:49:48'),(59,'Lorena','Arjona','larjona@banesco.com','123456',NULL,NULL,14,NULL,NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:49:48'),(60,'Ana','Castillo','ancastillo@banesco.com','123456',NULL,NULL,14,NULL,NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:49:48'),(61,'Estela','Duque','eduque@banesco.com','123456',NULL,NULL,14,NULL,NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:49:48'),(62,'Mariel','Gutierrez','mgutierrezv@banesco.com','123456',NULL,NULL,14,NULL,NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:49:48'),(63,'Nancy','Guerra','nguerra@banesco.com','123456',NULL,NULL,14,'6580-1729',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:49:48'),(64,'Edwin','Arrocha','earrocha@banesco.com','123456',NULL,NULL,14,'6006-5219',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:49:48'),(65,'Marta','Salazar','mlsalazar@banesco.com','123456',NULL,NULL,14,NULL,NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:49:48'),(66,'Aytchel','Ortega','Akortega@banesco.com','123456',NULL,NULL,14,NULL,NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:49:48'),(67,'Alexandra','De Freitas','alexandra.defreitas@afinitifinancial.com','123456',NULL,NULL,15,'6788-4481',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:49:48'),(68,'Ingri','Rodriguez','Ingri.Rodriguez@davivienda.com.pa','123456',NULL,NULL,16,'6550-4965',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:49:48'),(69,'Lenia','Dumasa','lenia.dumasa@davivienda.com.pa','123456',NULL,NULL,16,'6747-4243',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:49:48'),(70,'Isis','Barria','Ibarria@fpacifico.com','123456','','',17,'6672-1814',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:55:23'),(71,'Automarket','Corporacion de Credito','automarket@corporaciondecredito.com','123456','','',18,'',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:54:40'),(72,'Glafira','Portuondo','gportuondo@corporaciondecredito.com','123456','','',18,'6510-6868',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:54:28'),(73,'Armando','Quiel','aquiel@multifinanciamientos.com','123456','','',19,'6450-0725',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:53:03'),(74,'Carlos','Quintanilla','cquintana@multifinanciamientos.com','123456','','',19,'6931-3270',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:52:43'),(75,'Lineth','Rojas','lrojas@isthmuscap.com','123456','','',20,'6201-8464',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:54:13'),(76,'Dayana','Valdes','dvaldes@isthmuscap.com','123456','','',20,'6308-4769',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:54:02'),(77,'Stephany','Meja','Stephany@Fostrian.com','123456','','',21,'6156-9787',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:53:42'),(78,'Cristin','Garcia','cgracia@fostrian.com','123456','','',21,'',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:53:27'),(79,'Susseth','Garca','ventaslabendicion352@gmail.com','123456','','',22,'6021-2022',NULL,NULL,1,1,'2026-03-09 14:49:48','2026-03-09 14:51:17');

-- -----------------------------------------------------------------------------
-- 4. SOLICITUDES_CREDITO (depende de usuarios)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `solicitudes_credito`;
CREATE TABLE `solicitudes_credito` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gestor_id` int NOT NULL,
  `banco_id` int DEFAULT NULL,
  `evaluacion_seleccionada` int DEFAULT NULL,
  `evaluacion_en_reevaluacion` int DEFAULT NULL,
  `fecha_aprobacion_propuesta` timestamp NULL DEFAULT NULL,
  `comentario_seleccion_propuesta` text COLLATE utf8mb4_unicode_ci,
  `vendedor_id` int DEFAULT NULL,
  `tipo_persona` enum('Natural','Juridica') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_cliente` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cedula` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `edad` int DEFAULT NULL,
  `genero` enum('Masculino','Femenino','Otro') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono_principal` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_pipedrive` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_cliente_pipedrive` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_deal_pipedrive` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `forma_pago_pipedrive` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` text COLLATE utf8mb4_unicode_ci,
  `provincia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `distrito` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `corregimiento` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barriada` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `casa_edif` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero_casa_apto` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `casado` tinyint(1) DEFAULT '0',
  `hijos` int DEFAULT '0',
  `perfil_financiero` enum('Asalariado','Jubilado','Independiente') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ingreso` decimal(15,2) DEFAULT NULL,
  `tiempo_laborar` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profesion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ocupacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre_empresa_negocio` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estabilidad_laboral` date DEFAULT NULL,
  `fecha_constitucion` date DEFAULT NULL,
  `continuidad_laboral` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marca_auto` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modelo_auto` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `año_auto` int DEFAULT NULL,
  `kilometraje` int DEFAULT NULL,
  `precio_especial` decimal(15,2) DEFAULT NULL,
  `abono_porcentaje` decimal(5,2) DEFAULT NULL,
  `abono_monto` decimal(15,2) DEFAULT NULL,
  `comentarios_gestor` text COLLATE utf8mb4_unicode_ci,
  `ejecutivo_banco` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `respuesta_banco` enum('Pendiente','Aprobado','Pre Aprobado','Aprobado Condicional','Rechazado') COLLATE utf8mb4_unicode_ci DEFAULT 'Pendiente',
  `letra` decimal(15,2) DEFAULT NULL,
  `plazo` int DEFAULT NULL,
  `abono_banco` decimal(15,2) DEFAULT NULL,
  `promocion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comentarios_ejecutivo_banco` text COLLATE utf8mb4_unicode_ci,
  `respuesta_cliente` enum('Pendiente','Acepta','Rechaza') COLLATE utf8mb4_unicode_ci DEFAULT 'Pendiente',
  `motivo_respuesta` text COLLATE utf8mb4_unicode_ci,
  `fecha_envio_proforma` date DEFAULT NULL,
  `fecha_firma_cliente` date DEFAULT NULL,
  `fecha_poliza` date DEFAULT NULL,
  `fecha_carta_promesa` date DEFAULT NULL,
  `comentarios_fi` text COLLATE utf8mb4_unicode_ci,
  `estado` enum('Nueva','En Revisión Banco','Aprobada','Rechazada','Completada','Desistimiento') COLLATE utf8mb4_unicode_ci DEFAULT 'Nueva',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gestor` (`gestor_id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_respuesta_banco` (`respuesta_banco`),
  KEY `idx_fecha_creacion` (`fecha_creacion`),
  KEY `idx_email` (`email`),
  KEY `idx_cedula` (`cedula`),
  KEY `idx_banco` (`banco_id`),
  KEY `idx_vendedor` (`vendedor_id`),
  KEY `idx_evaluacion_seleccionada` (`evaluacion_seleccionada`),
  KEY `idx_evaluacion_reevaluacion` (`evaluacion_en_reevaluacion`),
  KEY `idx_fecha_aprobacion_propuesta` (`fecha_aprobacion_propuesta`)
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `solicitudes_credito` VALUES (76,1,NULL,NULL,NULL,NULL,NULL,NULL,'Natural','Lily Gilontas','PIPEDRIVE-ac7d52e0-73d7-11ec-878f-7fc0005ee0ad',NULL,NULL,'',NULL,'',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,'Asalariado',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Lead importado desde Pipedrive (Lead ID: ac7d52e0-73d7-11ec-878f-7fc0005ee0ad, Persona ID: 76046)',NULL,'Pendiente',NULL,NULL,NULL,NULL,NULL,'Pendiente',NULL,NULL,NULL,NULL,NULL,NULL,'Nueva','2026-02-11 22:01:19','2026-02-11 22:01:19'),(77,1,NULL,NULL,NULL,NULL,NULL,NULL,'Natural','Lily Gilontas','PIPEDRIVE-ac7d52e0-73d7-11ec-878f-7fc0005ee0ad',NULL,NULL,'',NULL,'',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,'Asalariado',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Lead importado desde Pipedrive (Lead ID: ac7d52e0-73d7-11ec-878f-7fc0005ee0ad, Persona ID: 76046)',NULL,'Pendiente',NULL,NULL,NULL,NULL,NULL,'Pendiente',NULL,NULL,NULL,NULL,NULL,NULL,'En Revisión Banco','2026-02-11 22:02:46','2026-02-27 16:58:36'),(90,22,NULL,NULL,NULL,NULL,NULL,NULL,'Natural','Daniel Maldonado','PIPEDRIVE-bf791320-e136-11ed-b425-87e40197cf1f',NULL,NULL,'65872700',NULL,'danielmaldonadov@gmail.com',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,'Asalariado',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Lead importado desde Pipedrive (Lead ID: bf791320-e136-11ed-b425-87e40197cf1f, Persona ID: 103019)',NULL,'Pendiente',NULL,NULL,NULL,NULL,NULL,'Pendiente',NULL,NULL,NULL,NULL,NULL,NULL,'Nueva','2026-03-06 16:32:12','2026-03-06 16:32:12');

-- -----------------------------------------------------------------------------
-- 5. VEHICULOS_SOLICITUD (depende de solicitudes_credito)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `vehiculos_solicitud`;
CREATE TABLE `vehiculos_solicitud` (
  `id` int NOT NULL AUTO_INCREMENT,
  `solicitud_id` int NOT NULL,
  `marca` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modelo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `anio` int DEFAULT NULL,
  `kilometraje` int DEFAULT NULL,
  `precio` decimal(15,2) DEFAULT NULL,
  `abono_porcentaje` decimal(5,2) DEFAULT NULL,
  `abono_monto` decimal(15,2) DEFAULT NULL,
  `orden` int DEFAULT '1',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_solicitud` (`solicitud_id`),
  KEY `idx_orden` (`orden`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. USUARIOS_BANCO_SOLICITUDES (depende de solicitudes_credito, usuarios)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `usuarios_banco_solicitudes`;
CREATE TABLE `usuarios_banco_solicitudes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `solicitud_id` int NOT NULL,
  `usuario_banco_id` int NOT NULL,
  `estado` enum('activo','inactivo') COLLATE utf8mb4_general_ci DEFAULT 'activo',
  `fecha_asignacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_desactivacion` timestamp NULL DEFAULT NULL,
  `creado_por` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_asignacion` (`solicitud_id`,`usuario_banco_id`),
  KEY `creado_por` (`creado_por`),
  KEY `idx_usuarios_banco_solicitud` (`solicitud_id`),
  KEY `idx_usuarios_banco_usuario` (`usuario_banco_id`),
  KEY `idx_usuarios_banco_estado` (`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `usuarios_banco_solicitudes` VALUES (31,77,25,'activo','2026-02-27 16:58:36',NULL,1);

-- -----------------------------------------------------------------------------
-- 7. EVALUACIONES_BANCO (depende de solicitudes, usuarios_banco_solicitudes, vehiculos)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `evaluaciones_banco`;
CREATE TABLE `evaluaciones_banco` (
  `id` int NOT NULL AUTO_INCREMENT,
  `solicitud_id` int NOT NULL,
  `vehiculo_id` int DEFAULT NULL,
  `usuario_banco_id` int NOT NULL,
  `decision` enum('preaprobado','aprobado','aprobado_condicional','rechazado') COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor_financiar` decimal(15,2) DEFAULT NULL,
  `abono` decimal(15,2) DEFAULT NULL,
  `plazo` int DEFAULT NULL,
  `letra` decimal(15,2) DEFAULT NULL,
  `promocion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tasa_bancaria` decimal(6,2) NOT NULL DEFAULT 0 COMMENT 'Tasa nominal anual (%)',
  `comentarios` text COLLATE utf8mb4_unicode_ci,
  `comentario_reevaluacion_solicitada` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Motivo al solicitar reevaluación (gestor/admin)',
  `fecha_solicitud_reevaluacion` datetime DEFAULT NULL,
  `fecha_evaluacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_solicitud` (`solicitud_id`),
  KEY `idx_usuario_banco` (`usuario_banco_id`),
  KEY `idx_decision` (`decision`),
  KEY `idx_fecha` (`fecha_evaluacion`),
  KEY `idx_vehiculo` (`vehiculo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. ADJUNTOS_SOLICITUD
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `adjuntos_solicitud`;
CREATE TABLE `adjuntos_solicitud` (
  `id` int NOT NULL AUTO_INCREMENT,
  `solicitud_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `nombre_archivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruta_archivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_archivo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tamaño_archivo` int NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `fecha_subida` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_solicitud` (`solicitud_id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_fecha` (`fecha_subida`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. CITAS_FIRMA
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `citas_firma`;
CREATE TABLE `citas_firma` (
  `id` int NOT NULL AUTO_INCREMENT,
  `solicitud_id` int NOT NULL,
  `fecha_cita` date NOT NULL,
  `hora_cita` time NOT NULL,
  `comentarios` text COLLATE utf8mb4_unicode_ci,
  `asistio` enum('pendiente','asistio','no_asistio') COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_solicitud` (`solicitud_id`),
  KEY `idx_fecha_cita` (`fecha_cita`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. DOCUMENTOS_SOLICITUD
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `documentos_solicitud`;
CREATE TABLE `documentos_solicitud` (
  `id` int NOT NULL AUTO_INCREMENT,
  `solicitud_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `nombre_archivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruta_archivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_documento` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamaño_archivo` int DEFAULT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_solicitud` (`solicitud_id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_fecha` (`fecha_subida`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 11. ESTADISTICAS_IMPORTACION
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `estadisticas_importacion`;
CREATE TABLE `estadisticas_importacion` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fecha_importacion` date NOT NULL,
  `total_importados` int DEFAULT '0',
  `total_errores` int DEFAULT '0',
  `archivo_original` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` int DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fecha` (`fecha_importacion`),
  KEY `idx_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 12. MENSAJES_SOLICITUD
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `mensajes_solicitud`;
CREATE TABLE `mensajes_solicitud` (
  `id` int NOT NULL AUTO_INCREMENT,
  `solicitud_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `mensaje` text COLLATE utf8mb4_general_ci NOT NULL,
  `tipo` enum('general','banco','gestor') COLLATE utf8mb4_general_ci DEFAULT 'general',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_mensajes_solicitud` (`solicitud_id`),
  KEY `idx_mensajes_fecha` (`fecha_creacion`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 13. NOTAS_SOLICITUD
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `notas_solicitud`;
CREATE TABLE `notas_solicitud` (
  `id` int NOT NULL AUTO_INCREMENT,
  `solicitud_id` int NOT NULL,
  `vehiculo_id` int DEFAULT NULL,
  `usuario_id` int NOT NULL,
  `usuario_banco_id` int DEFAULT NULL,
  `tipo_nota` enum('Comentario','Actualización','Documento','Respuesta Banco','Respuesta Cliente') COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contenido` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_solicitud` (`solicitud_id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_fecha` (`fecha_creacion`),
  KEY `idx_usuario_banco` (`usuario_banco_id`),
  KEY `idx_vehiculo` (`vehiculo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=206 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `notas_solicitud` VALUES (162,76,NULL,1,NULL,'Actualización','Lead Importado','Lead importado desde Pipedrive (Lead ID: ac7d52e0-73d7-11ec-878f-7fc0005ee0ad, Persona ID: 76046)','2026-02-11 22:01:19'),(163,77,NULL,1,NULL,'Actualización','Lead Importado','Lead importado desde Pipedrive (Lead ID: ac7d52e0-73d7-11ec-878f-7fc0005ee0ad, Persona ID: 76046)','2026-02-11 22:02:46'),(164,77,NULL,1,NULL,'Actualización','Solicitud enviada a revisión bancaria','Solicitud asignada al usuario banco: Itzel Rodriguez (Banco Nacional de Panamá). Estado cambiado a \'En Revisión Banco\'.','2026-02-27 16:58:36'),(177,90,NULL,22,NULL,'Actualización','Lead Importado','Lead importado desde Pipedrive (Lead ID: bf791320-e136-11ed-b425-87e40197cf1f, Persona ID: 103019)','2026-03-06 16:32:12');

-- -----------------------------------------------------------------------------
-- 13b. HISTORIAL_SOLICITUD (depende de solicitudes_credito, usuarios)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `historial_solicitud`;
CREATE TABLE `historial_solicitud` (
  `id` int NOT NULL AUTO_INCREMENT,
  `solicitud_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `tipo_accion` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'creacion, cambio_estado, documento_agregado, asignacion_banco, actualizacion_datos, evaluacion_banco',
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado_anterior` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado_nuevo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_solicitud` (`solicitud_id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_fecha` (`fecha_creacion`),
  KEY `idx_tipo` (`tipo_accion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 14. USUARIO_ROLES (depende de usuarios, roles)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `usuario_roles`;
CREATE TABLE `usuario_roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `rol_id` int NOT NULL,
  `fecha_asignacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_usuario_rol` (`usuario_id`,`rol_id`),
  KEY `rol_id` (`rol_id`)
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `usuario_roles` VALUES (1,1,1,'2025-08-13 22:10:58'),(39,22,7,'2025-11-26 18:53:29'),(40,23,7,'2025-11-26 18:55:46'),(43,26,8,'2026-03-09 13:44:40'),(44,25,7,'2026-03-09 13:45:03'),(45,24,7,'2026-03-09 13:45:15'),(46,27,8,'2026-03-09 13:46:23'),(48,28,8,'2026-03-09 13:48:15'),(49,29,8,'2026-03-09 13:49:58'),(50,30,8,'2026-03-09 13:52:20'),(51,31,8,'2026-03-09 13:54:28'),(52,32,8,'2026-03-09 13:55:53'),(53,33,8,'2026-03-09 13:58:17'),(54,34,8,'2026-03-09 14:01:21'),(55,35,8,'2026-03-09 14:03:05'),(57,36,8,'2026-03-09 14:17:25'),(58,37,8,'2026-03-09 14:18:46'),(59,38,8,'2026-03-09 14:21:15'),(60,39,8,'2026-03-09 14:23:37'),(62,40,8,'2026-03-09 14:24:58'),(63,41,8,'2026-03-09 14:26:32'),(64,42,8,'2026-03-09 14:28:03'),(65,43,8,'2026-03-09 14:30:14'),(68,79,8,'2026-03-09 14:51:49'),(69,74,8,'2026-03-09 14:52:43'),(70,73,8,'2026-03-09 14:53:03'),(71,78,8,'2026-03-09 14:53:27'),(72,77,8,'2026-03-09 14:53:42'),(73,76,8,'2026-03-09 14:54:02'),(74,75,8,'2026-03-09 14:54:13'),(75,72,8,'2026-03-09 14:54:28'),(76,71,8,'2026-03-09 14:54:40'),(77,70,8,'2026-03-09 14:55:23'),(79,45,8,'2026-03-09 15:00:22'),(80,46,8,'2026-03-09 15:00:22'),(81,47,8,'2026-03-09 15:00:22'),(83,49,8,'2026-03-09 15:00:22'),(86,52,8,'2026-03-09 15:00:22'),(87,53,8,'2026-03-09 15:00:22'),(88,54,8,'2026-03-09 15:00:22'),(90,56,8,'2026-03-09 15:00:22'),(91,57,8,'2026-03-09 15:00:22'),(92,58,8,'2026-03-09 15:00:22'),(93,59,8,'2026-03-09 15:00:22'),(94,60,8,'2026-03-09 15:00:22'),(95,61,8,'2026-03-09 15:00:22'),(96,62,8,'2026-03-09 15:00:22'),(97,63,8,'2026-03-09 15:00:22'),(98,64,8,'2026-03-09 15:00:22'),(99,65,8,'2026-03-09 15:00:22'),(100,66,8,'2026-03-09 15:00:22'),(101,67,8,'2026-03-09 15:00:22'),(102,68,8,'2026-03-09 15:00:22'),(103,69,8,'2026-03-09 15:00:22'),(104,44,8,'2026-03-09 15:37:18'),(105,48,8,'2026-03-09 15:39:42'),(106,50,8,'2026-03-09 15:41:07'),(107,51,8,'2026-03-09 15:42:00'),(108,55,8,'2026-03-09 15:42:45');

-- =============================================================================
-- AGREGAR RELACIONES (FOREIGN KEYS) AL FINAL
-- =============================================================================

ALTER TABLE `usuarios` ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`banco_id`) REFERENCES `bancos` (`id`) ON DELETE SET NULL;

ALTER TABLE `solicitudes_credito` ADD CONSTRAINT `fk_solicitudes_banco` FOREIGN KEY (`banco_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;
ALTER TABLE `solicitudes_credito` ADD CONSTRAINT `fk_solicitudes_vendedor` FOREIGN KEY (`vendedor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;
ALTER TABLE `solicitudes_credito` ADD CONSTRAINT `solicitudes_credito_ibfk_1` FOREIGN KEY (`gestor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
ALTER TABLE `solicitudes_credito` ADD CONSTRAINT `solicitudes_credito_ibfk_2` FOREIGN KEY (`evaluacion_seleccionada`) REFERENCES `evaluaciones_banco` (`id`) ON DELETE SET NULL;
ALTER TABLE `solicitudes_credito` ADD CONSTRAINT `solicitudes_credito_ibfk_3` FOREIGN KEY (`evaluacion_en_reevaluacion`) REFERENCES `evaluaciones_banco` (`id`) ON DELETE SET NULL;

ALTER TABLE `vehiculos_solicitud` ADD CONSTRAINT `vehiculos_solicitud_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_credito` (`id`) ON DELETE CASCADE;

ALTER TABLE `usuarios_banco_solicitudes` ADD CONSTRAINT `usuarios_banco_solicitudes_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_credito` (`id`) ON DELETE CASCADE;
ALTER TABLE `usuarios_banco_solicitudes` ADD CONSTRAINT `usuarios_banco_solicitudes_ibfk_2` FOREIGN KEY (`usuario_banco_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
ALTER TABLE `usuarios_banco_solicitudes` ADD CONSTRAINT `usuarios_banco_solicitudes_ibfk_3` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

ALTER TABLE `evaluaciones_banco` ADD CONSTRAINT `evaluaciones_banco_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_credito` (`id`) ON DELETE CASCADE;
ALTER TABLE `evaluaciones_banco` ADD CONSTRAINT `evaluaciones_banco_ibfk_2` FOREIGN KEY (`usuario_banco_id`) REFERENCES `usuarios_banco_solicitudes` (`id`) ON DELETE CASCADE;
ALTER TABLE `evaluaciones_banco` ADD CONSTRAINT `evaluaciones_banco_ibfk_3` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos_solicitud` (`id`) ON DELETE CASCADE;

ALTER TABLE `adjuntos_solicitud` ADD CONSTRAINT `adjuntos_solicitud_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_credito` (`id`) ON DELETE CASCADE;
ALTER TABLE `adjuntos_solicitud` ADD CONSTRAINT `adjuntos_solicitud_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

ALTER TABLE `citas_firma` ADD CONSTRAINT `citas_firma_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_credito` (`id`) ON DELETE CASCADE;

ALTER TABLE `documentos_solicitud` ADD CONSTRAINT `documentos_solicitud_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_credito` (`id`) ON DELETE CASCADE;
ALTER TABLE `documentos_solicitud` ADD CONSTRAINT `documentos_solicitud_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

ALTER TABLE `estadisticas_importacion` ADD CONSTRAINT `estadisticas_importacion_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

ALTER TABLE `mensajes_solicitud` ADD CONSTRAINT `mensajes_solicitud_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_credito` (`id`) ON DELETE CASCADE;
ALTER TABLE `mensajes_solicitud` ADD CONSTRAINT `mensajes_solicitud_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

ALTER TABLE `notas_solicitud` ADD CONSTRAINT `fk_nota_usuario_banco` FOREIGN KEY (`usuario_banco_id`) REFERENCES `usuarios_banco_solicitudes` (`id`) ON DELETE CASCADE;
ALTER TABLE `notas_solicitud` ADD CONSTRAINT `fk_nota_vehiculo` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos_solicitud` (`id`) ON DELETE CASCADE;
ALTER TABLE `notas_solicitud` ADD CONSTRAINT `notas_solicitud_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_credito` (`id`) ON DELETE CASCADE;
ALTER TABLE `notas_solicitud` ADD CONSTRAINT `notas_solicitud_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

ALTER TABLE `historial_solicitud` ADD CONSTRAINT `historial_solicitud_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_credito` (`id`) ON DELETE CASCADE;
ALTER TABLE `historial_solicitud` ADD CONSTRAINT `historial_solicitud_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

ALTER TABLE `usuario_roles` ADD CONSTRAINT `usuario_roles_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
ALTER TABLE `usuario_roles` ADD CONSTRAINT `usuario_roles_ibfk_2` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

-- =============================================================================
SET FOREIGN_KEY_CHECKS = 1;
SET UNIQUE_CHECKS = 1;
-- Fin
-- =============================================================================

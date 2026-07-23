-- MySQL dump 10.13  Distrib 8.4.8, for Linux (x86_64)
--
-- Host: 127.0.0.1    Database: sietelsa
-- ------------------------------------------------------
-- Server version	8.4.8-0ubuntu0.25.10.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `configuracion_sistema`
--

DROP TABLE IF EXISTS `configuracion_sistema`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuracion_sistema` (
  `clave` varchar(80) NOT NULL,
  `valor` varchar(255) NOT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configuracion_sistema`
--

LOCK TABLES `configuracion_sistema` WRITE;
/*!40000 ALTER TABLE `configuracion_sistema` DISABLE KEYS */;
INSERT INTO `configuracion_sistema` VALUES ('mantenimiento_activo','0','2026-02-28 03:10:38');
/*!40000 ALTER TABLE `configuracion_sistema` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contacto_mensajes`
--

DROP TABLE IF EXISTS `contacto_mensajes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contacto_mensajes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `asunto` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mensaje` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nuevo',
  `respuesta_admin` text COLLATE utf8mb4_unicode_ci,
  `respondido_por_usuario_id` int unsigned DEFAULT NULL,
  `respondido_en` datetime DEFAULT NULL,
  `notificado` tinyint(1) NOT NULL DEFAULT '0',
  `error_notificacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_origen` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contacto_estado_creado` (`estado`,`creado_en`),
  KEY `fk_contacto_respondido_por_usuario` (`respondido_por_usuario_id`),
  CONSTRAINT `fk_contacto_respondido_por_usuario` FOREIGN KEY (`respondido_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contacto_mensajes`
--

LOCK TABLES `contacto_mensajes` WRITE;
/*!40000 ALTER TABLE `contacto_mensajes` DISABLE KEYS */;
INSERT INTO `contacto_mensajes` VALUES (13,'Milton','rivasmilton513@gmail.com','61156808','prueba1','Prueba funcional','respondido','Funcional',1,'2026-02-25 19:44:56',1,NULL,'190.150.121.76','2026-02-25 19:43:51','2026-02-25 19:44:56');
/*!40000 ALTER TABLE `contacto_mensajes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `modulos`
--

DROP TABLE IF EXISTS `modulos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modulos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nombre_modulo` varchar(80) NOT NULL,
  `descripcion` varchar(150) DEFAULT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre_modulo` (`nombre_modulo`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modulos`
--

LOCK TABLES `modulos` WRITE;
/*!40000 ALTER TABLE `modulos` DISABLE KEYS */;
INSERT INTO `modulos` VALUES (1,'ver_dashboard','Puede ingresar al dashboard','activo','2026-02-28 03:52:32'),(2,'gestionar_usuarios','Puede crear o editar usuarios','activo','2026-02-28 03:52:32'),(3,'ver_reportes','Puede ver reportes','activo','2026-02-28 03:52:32'),(4,'gestionar_contenido','Permiso general heredado para gestionar contenido','activo','2026-02-28 03:52:32'),(5,'gestionar_servicios','Puede administrar el modulo de servicios','activo','2026-02-28 03:52:32'),(6,'gestionar_portafolio','Puede administrar el modulo de portafolio','activo','2026-02-28 03:52:32'),(7,'gestionar_proyectos','Puede administrar el modulo de proyectos','activo','2026-02-28 03:52:32'),(8,'gestionar_nosotros','Puede administrar el modulo de nosotros','activo','2026-02-28 03:52:32'),(9,'gestionar_contacto','Puede gestionar los mensajes de contacto','activo','2026-02-28 03:52:32'),(10,'gestionar_backup','Puede generar y descargar respaldos SQL','activo','2026-02-28 03:52:32'),(11,'gestionar_perfiles','Puede crear, editar o eliminar perfiles','activo','2026-02-28 03:52:32');
/*!40000 ALTER TABLE `modulos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `nosotros`
--

DROP TABLE IF EXISTS `nosotros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nosotros` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion_1` text COLLATE utf8mb4_unicode_ci,
  `descripcion_2` text COLLATE utf8mb4_unicode_ci,
  `descripcion_3` text COLLATE utf8mb4_unicode_ci,
  `imagen_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden` int unsigned NOT NULL DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `nosotros`
--

LOCK TABLES `nosotros` WRITE;
/*!40000 ALTER TABLE `nosotros` DISABLE KEYS */;
INSERT INTO `nosotros` VALUES (1,'NOSOTROS','SIETELSA, S.A. DE C.V. Nace en 2005 con la objetivo de otorgar soluciones de alto nivel a empresas nacionales y   multinacionales,  para todo tipo de proyectos de Telecomunicaciones y Electricidad.','A través de los años, la empresa ha desempeñado trabajos con eficacia y eficiencia, impulsando siempre calidad de servicio dentro de los plazos solicitados por nuestros clientes, todo esto nos ha permitido lograr un gran crecimiento.','Presencia en Centro América: El Salvador (HC), Guatemala, Honduras y Nicaragua','assets/img/about/upload_nosotros_20260224_205551_d2a4db7c.png',1,1,'2026-02-24 13:42:27','2026-02-24 13:56:37');
/*!40000 ALTER TABLE `nosotros` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `perfil_modulo`
--

DROP TABLE IF EXISTS `perfil_modulo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `perfil_modulo` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `perfil_id` int unsigned NOT NULL,
  `modulo_id` int unsigned NOT NULL,
  `aprobado` tinyint(1) NOT NULL DEFAULT '1',
  `fecha_asignacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_perfil_modulo` (`perfil_id`,`modulo_id`),
  KEY `fk_perfil_modulo_modulo` (`modulo_id`),
  CONSTRAINT `fk_perfil_modulo_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `modulos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_perfil_modulo_perfil` FOREIGN KEY (`perfil_id`) REFERENCES `perfiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `perfil_modulo`
--

LOCK TABLES `perfil_modulo` WRITE;
/*!40000 ALTER TABLE `perfil_modulo` DISABLE KEYS */;
INSERT INTO `perfil_modulo` VALUES (1,1,10,1,'2026-02-28 04:35:41'),(2,1,9,1,'2026-02-28 04:35:41'),(3,1,4,1,'2026-02-28 04:35:41'),(4,1,8,1,'2026-02-28 04:35:41'),(5,1,11,1,'2026-02-28 04:35:41'),(6,1,6,1,'2026-02-28 04:35:41'),(7,1,7,1,'2026-02-28 04:35:41'),(8,1,5,1,'2026-02-28 04:35:41'),(9,1,2,1,'2026-02-28 04:35:41'),(10,1,1,1,'2026-02-28 04:35:41'),(11,1,3,1,'2026-02-28 04:35:41');
/*!40000 ALTER TABLE `perfil_modulo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `perfil_permiso`
--

DROP TABLE IF EXISTS `perfil_permiso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `perfil_permiso` (
  `perfil_id` int unsigned NOT NULL,
  `permiso_id` int unsigned NOT NULL,
  PRIMARY KEY (`perfil_id`,`permiso_id`),
  KEY `fk_perfil_permiso_permiso` (`permiso_id`),
  CONSTRAINT `fk_perfil_permiso_perfil` FOREIGN KEY (`perfil_id`) REFERENCES `perfiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_perfil_permiso_permiso` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `perfil_permiso`
--

LOCK TABLES `perfil_permiso` WRITE;
/*!40000 ALTER TABLE `perfil_permiso` DISABLE KEYS */;
INSERT INTO `perfil_permiso` VALUES (1,1),(1,2),(1,3),(1,4),(1,949),(1,950),(1,951),(1,952),(1,953),(1,954),(1,6019);
/*!40000 ALTER TABLE `perfil_permiso` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `perfiles`
--

DROP TABLE IF EXISTS `perfiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `perfiles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=4148 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `perfiles`
--

LOCK TABLES `perfiles` WRITE;
/*!40000 ALTER TABLE `perfiles` DISABLE KEYS */;
INSERT INTO `perfiles` VALUES (1,'admin','Control total del sistema',1,'2026-02-23 18:27:50');
/*!40000 ALTER TABLE `perfiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permisos`
--

DROP TABLE IF EXISTS `permisos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permisos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=9947 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permisos`
--

LOCK TABLES `permisos` WRITE;
/*!40000 ALTER TABLE `permisos` DISABLE KEYS */;
INSERT INTO `permisos` VALUES (1,'ver_dashboard','Puede ingresar al dashboard'),(2,'gestionar_usuarios','Puede crear o editar usuarios'),(3,'ver_reportes','Puede ver reportes'),(4,'gestionar_contenido','Permiso general heredado para gestionar contenido'),(949,'gestionar_servicios','Puede administrar el modulo de servicios'),(950,'gestionar_portafolio','Puede administrar el modulo de portafolio'),(951,'gestionar_proyectos','Puede administrar el modulo de proyectos'),(952,'gestionar_nosotros','Puede administrar el modulo de nosotros'),(953,'gestionar_contacto','Puede gestionar los mensajes de contacto'),(954,'gestionar_backup','Puede generar y descargar respaldos SQL'),(6019,'gestionar_perfiles','Puede crear, editar o eliminar perfiles');
/*!40000 ALTER TABLE `permisos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `portafolio`
--

DROP TABLE IF EXISTS `portafolio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portafolio` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(1200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cliente` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `categoria` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imagen_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `orden` int unsigned NOT NULL DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portafolio`
--

LOCK TABLES `portafolio` WRITE;
/*!40000 ALTER TABLE `portafolio` DISABLE KEYS */;
INSERT INTO `portafolio` VALUES (2,'Redes de Telecomunicación móvil','Nuestros Servicios de Telecomunicación Móvil\r\n\r\nInstalación, Comisionamiento y Mantenimiento de Radiobases 2G, 3G y 4G\r\n\r\nInstalación y Comisionamiento de Enlaces de Microondas\r\n\r\nInstalación y Mantenimiento de Redes de Fibra Óptica\r\n\r\nConstrucción y Mantenimiento de Torres y Monopolos de hasta 60 Metros\r\n\r\nInstalación, Comisionamiento y Mantenimiento de Sistemas de Energía para Telecomunicaciones\r\n\r\nInstalación y Mantenimiento de Motogeneradores de hasta 750 KVA con Sistema de Transferencia\r\n\r\nInstalación y Mantenimiento de Tableros de Distribución de Baja Tensión y Sistemas de Rectificación',NULL,NULL,'assets/img/portfolio/upload_portafolio_20260224_171645_fd06c68f.jpg',1,1,'2026-02-24 10:16:45','2026-02-24 10:16:45'),(3,'Sistemas Eléctricos de baja y media tensión','Nuestros Servicios en Sistemas Eléctricos\r\n\r\nDiseño, Supervisión y Construcción de Líneas Eléctricas\r\n\r\nMedición, Instalación y Mantenimiento de Redes de Tierra\r\n\r\nDiseño, Instalación y Mantenimiento de Subestaciones Eléctricas (Media y Alta Tensión)\r\n\r\nInstalación de Acometidas Eléctricas e Instalaciones Residenciales\r\n\r\nInstalación de Sistemas de Automatización\r\n\r\nConstrucción, Mantenimiento y Poda de Sistemas de Línea\r\n\r\nObras Civiles (Bases, Cerramientos de Subestaciones, Remodelaciones y Otros)',NULL,NULL,'assets/img/portfolio/upload_portafolio_20260224_200350_98dd8465.jpg',2,1,'2026-02-24 13:03:50','2026-02-24 13:03:50'),(4,'Centrales de Generación solar','Nuestros Servicios en Centrales de Generación Solar\r\n\r\nDiseño y Construcción de Centrales Solares de 1 a 25 MW\r\n\r\nDesarrollo, Permisología y Ejecución de Planos Constructivos\r\n\r\nMontaje de Herrajería y Paneles Solares\r\n\r\nCanalizaciones y Cableado Eléctrico y de Comunicación, incluyendo Fibra Óptica\r\n\r\nDiseño y Construcción de Subestaciones para Centrales de Energía Solar',NULL,NULL,'assets/img/portfolio/upload_portafolio_20260224_201113_d11ea5e5.webp',3,1,'2026-02-24 13:11:13','2026-02-24 13:11:13'),(5,'Obras Cíviles generales','Nuestros Servicios en Construcción y Acabados Residenciales\r\n\r\nDiseño de Fachadas\r\n\r\nConstrucciones Residenciales\r\n\r\nTrabajos de Pintura Residencial',NULL,NULL,'assets/img/portfolio/upload_portafolio_20260224_201314_ef3ad251.jpg',4,1,'2026-02-24 13:13:14','2026-02-24 13:13:14'),(6,'Servicios de Radiofrecuencias','Nuestros Servicios en Radiofrecuencia\r\n\r\nDiseño de Radioenlaces de Microondas en Banda Libre y Licenciada\r\n\r\nAuditoría de Redes de Microondas Existentes y Optimización de Licenciamiento\r\n\r\nDiseño, Sintonía Inicial y Optimización de Bases de Comunicación Móvil (DECT, 2G, 3G y 4G)\r\n\r\nAnálisis de Espectro y Asesoría en Temas de Interferencia ante SIGET\r\n\r\nMedición de Sistemas de Tierra en Redes de Telecomunicaciones\r\n\r\nAuditoría de Ocupación de Torres\r\n\r\nDiseño de Redes WiFi 5 GHz Outdoor y Enlaces Punto a Multipunto',NULL,NULL,'assets/img/portfolio/upload_portafolio_20260224_201620_324728e7.jpg',5,1,'2026-02-24 13:16:20','2026-02-24 13:16:20'),(7,'Servicios de Fibra óptica','Nuestros Servicios en Fibra Óptica\r\n\r\nImplementación de Anillos de Fibra Óptica\r\n\r\nDiseño y Colocación de Fibra Óptica\r\n\r\nReparación de Enlaces de Fibra Óptica',NULL,NULL,'assets/img/portfolio/upload_portafolio_20260224_201835_d39fb3f1.jpg',6,1,'2026-02-24 13:18:35','2026-02-24 13:18:35');
/*!40000 ALTER TABLE `portafolio` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `portafolio_imagenes`
--

DROP TABLE IF EXISTS `portafolio_imagenes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portafolio_imagenes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `portafolio_id` int unsigned NOT NULL,
  `imagen_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `orden` int unsigned NOT NULL DEFAULT '0',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_portafolio_imagenes_portafolio` (`portafolio_id`),
  CONSTRAINT `fk_portafolio_imagenes_portafolio` FOREIGN KEY (`portafolio_id`) REFERENCES `portafolio` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portafolio_imagenes`
--

LOCK TABLES `portafolio_imagenes` WRITE;
/*!40000 ALTER TABLE `portafolio_imagenes` DISABLE KEYS */;
INSERT INTO `portafolio_imagenes` VALUES (1,2,'assets/img/portfolio/upload_portafolio_20260224_171645_fd06c68f.jpg',1,'2026-02-24 10:30:04'),(2,2,'assets/img/portfolio/upload_portafolio_20260224_195932_7305ce61.webp',2,'2026-02-24 12:59:32'),(3,2,'assets/img/portfolio/upload_portafolio_20260224_195944_d78aa6a9.webp',3,'2026-02-24 12:59:44'),(4,3,'assets/img/portfolio/upload_portafolio_20260224_200350_98dd8465.jpg',1,'2026-02-24 13:03:50'),(5,4,'assets/img/portfolio/upload_portafolio_20260224_201113_d11ea5e5.webp',1,'2026-02-24 13:11:13'),(6,5,'assets/img/portfolio/upload_portafolio_20260224_201314_ef3ad251.jpg',1,'2026-02-24 13:13:14'),(7,6,'assets/img/portfolio/upload_portafolio_20260224_201620_324728e7.jpg',1,'2026-02-24 13:16:20'),(8,7,'assets/img/portfolio/upload_portafolio_20260224_201835_d39fb3f1.jpg',1,'2026-02-24 13:18:35');
/*!40000 ALTER TABLE `portafolio_imagenes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proyectos`
--

DROP TABLE IF EXISTS `proyectos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proyectos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `portafolio_id` int unsigned DEFAULT NULL,
  `imagen_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `orden` int unsigned NOT NULL DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_proyectos_portafolio` (`portafolio_id`),
  CONSTRAINT `fk_proyectos_portafolio` FOREIGN KEY (`portafolio_id`) REFERENCES `portafolio` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proyectos`
--

LOCK TABLES `proyectos` WRITE;
/*!40000 ALTER TABLE `proyectos` DISABLE KEYS */;
INSERT INTO `proyectos` VALUES (1,'Redes móviles',2,'assets/img/proyectos/upload_proyecto_20260224_211927_9ea9d812.png',1,1,'2026-02-24 14:19:27','2026-02-24 14:35:27'),(2,'Sistemas eléctricos',3,'assets/img/proyectos/upload_proyecto_20260224_213902_a956ff5b.png',2,1,'2026-02-24 14:39:02','2026-02-24 14:39:02'),(3,'Centrales Solares',4,'assets/img/proyectos/upload_proyecto_20260224_213953_8a911232.png',3,1,'2026-02-24 14:39:53','2026-02-24 14:39:53');
/*!40000 ALTER TABLE `proyectos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `servicios`
--

DROP TABLE IF EXISTS `servicios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `servicios` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icono` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fa-circle-info',
  `orden` int unsigned NOT NULL DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `servicios`
--

LOCK TABLES `servicios` WRITE;
/*!40000 ALTER TABLE `servicios` DISABLE KEYS */;
INSERT INTO `servicios` VALUES (2,'Redes de Telecomunicación móvil',NULL,'fa-network-wired',1,1,'2026-02-23 23:56:58','2026-02-23 23:58:11'),(3,'Sistemas Eléctricos de baja y media tensión',NULL,'fa-gears',2,1,'2026-02-23 23:58:49','2026-02-23 23:59:49'),(4,'Centrales de Generación solar',NULL,'fa-bolt',3,1,'2026-02-24 00:06:00','2026-02-24 00:06:00'),(5,'Obras Civiles generales',NULL,'fa-shield-halved',4,1,'2026-02-24 00:16:59','2026-02-24 00:16:59'),(6,'Servicios de Radiofrecuencias',NULL,'fa-wifi',5,1,'2026-02-24 00:17:55','2026-02-24 00:17:55'),(7,'Servicios de Fibra óptica',NULL,'fa-database',6,1,'2026-02-24 00:18:14','2026-02-24 00:18:14');
/*!40000 ALTER TABLE `servicios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario_permiso`
--

DROP TABLE IF EXISTS `usuario_permiso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario_permiso` (
  `usuario_id` int unsigned NOT NULL,
  `permiso_id` int unsigned NOT NULL,
  `permitido` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`usuario_id`,`permiso_id`),
  KEY `fk_usuario_permiso_permiso` (`permiso_id`),
  CONSTRAINT `fk_usuario_permiso_permiso` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_usuario_permiso_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario_permiso`
--

LOCK TABLES `usuario_permiso` WRITE;
/*!40000 ALTER TABLE `usuario_permiso` DISABLE KEYS */;
INSERT INTO `usuario_permiso` VALUES (1,1,1),(1,2,1),(1,3,1),(1,949,1),(1,950,1),(1,951,1),(1,952,1),(1,953,1),(1,954,1),(1,6019,1),(765,1,1),(765,2,1),(765,3,1),(765,949,1),(765,950,1),(765,951,1),(765,952,1),(765,953,1),(765,954,1),(765,6019,1);
/*!40000 ALTER TABLE `usuario_permiso` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` enum('admin','usuario') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'usuario',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ultimo_login` datetime DEFAULT NULL,
  `intentos_fallidos` int unsigned NOT NULL DEFAULT '0',
  `bloqueado_hasta` datetime DEFAULT NULL,
  `perfil_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uq_usuarios_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=857 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'Administrador','admin','prueba@prueba.com','+50362565926','San Salvador','','$2y$12$z2UoVT15RMkIpQgypo/UCujda/ZwusPdZSfObdiSOpIzeBsn/Ix3.','admin',1,'2026-02-23 14:12:51','2026-02-28 04:40:52',NULL,0,NULL,1),(765,'Milton Guillermo Rivas','usr.916.786','rivasmilton513@gmail.com','+50361156808','San Salvador','','$2y$12$vXGmMEbwt2lf8TkAr/AvyeCnwrcyYgedcaM1Vtv5txiizIieNHQai','admin',1,'2026-02-28 03:38:14','2026-02-28 03:38:14',NULL,0,NULL,1);
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `visitas_sitio`
--

DROP TABLE IF EXISTS `visitas_sitio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `visitas_sitio` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pagina` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'inicio',
  `ip_origen` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pais_codigo` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pais_nombre` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_visitas_creado` (`creado_en`),
  KEY `idx_visitas_pagina_creado` (`pagina`,`creado_en`),
  KEY `idx_visitas_pais_creado` (`pais_codigo`,`creado_en`)
) ENGINE=InnoDB AUTO_INCREMENT=331 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `visitas_sitio`
--

LOCK TABLES `visitas_sitio` WRITE;
/*!40000 ALTER TABLE `visitas_sitio` DISABLE KEYS */;
INSERT INTO `visitas_sitio` VALUES (1,'inicio','::1',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0','2026-02-24 21:54:16'),(2,'inicio','::1',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0','2026-02-24 22:06:38'),(3,'inicio','::1',NULL,'Desconocido','Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1','2026-02-24 22:20:37'),(4,'inicio','::1',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-24 22:23:22'),(5,'inicio','::1',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-24 22:24:43'),(6,'inicio','::1',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-24 22:25:39'),(7,'inicio','::1',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-24 22:26:39'),(8,'inicio','::1',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0','2026-02-24 22:27:58'),(9,'inicio','::1',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-24 22:30:14'),(10,'inicio','::1',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-24 22:38:44'),(11,'inicio','::1',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-24 22:38:59'),(12,'inicio','::1',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-24 22:42:39'),(13,'inicio','::1',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-24 22:46:37'),(14,'inicio','::1',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-24 22:46:53'),(15,'inicio','::1',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-24 22:51:55'),(16,'inicio','::1',NULL,'Desconocido','Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1','2026-02-24 22:52:03'),(17,'inicio','::1',NULL,'Desconocido','Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1','2026-02-24 22:52:46'),(18,'inicio','::1',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-24 22:57:24'),(19,'inicio','::1',NULL,'Desconocido','Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1','2026-02-24 23:06:07'),(20,'inicio','::1',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-24 23:06:18'),(21,'inicio','::1',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-24 23:06:49'),(22,'inicio','::1',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-24 23:08:24'),(23,'inicio','::1',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0','2026-02-25 00:13:52'),(24,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-25 17:35:12'),(25,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-25 17:35:45'),(26,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 17:37:04'),(27,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 17:37:25'),(28,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 17:42:59'),(29,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-25 17:53:35'),(30,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-25 17:56:40'),(31,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-25 17:58:24'),(32,'inicio','190.150.106.51',NULL,'Desconocido','Mozilla/5.0 (iPhone; CPU iPhone OS 18_3_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.3 Mobile/15E148 Safari/604.1','2026-02-25 17:59:48'),(33,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 18:02:39'),(34,'inicio','200.89.84.109',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 18:54:17'),(35,'inicio','34.77.195.236',NULL,'Desconocido','python-requests/2.32.5','2026-02-25 18:54:50'),(36,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-25 19:42:51'),(37,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-25 19:43:03'),(38,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-25 19:43:53'),(39,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 19:47:37'),(40,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 19:48:28'),(41,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-25 19:50:08'),(42,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-25 19:51:44'),(43,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 19:51:48'),(44,'inicio','190.150.106.51',NULL,'Desconocido','Mozilla/5.0 (iPhone; CPU iPhone OS 18_3_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.3 Mobile/15E148 Safari/604.1','2026-02-25 20:26:38'),(45,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-25 20:33:05'),(46,'inicio','168.243.188.188',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 20:35:32'),(47,'inicio','66.249.83.8',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; +https://support.google.com/webmasters/answer/1061943)','2026-02-25 20:35:33'),(48,'inicio','66.249.83.8',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; +https://support.google.com/webmasters/answer/1061943)','2026-02-25 20:35:33'),(49,'inicio','66.249.83.9',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; +https://support.google.com/webmasters/answer/1061943)','2026-02-25 20:35:33'),(50,'inicio','103.4.250.5',NULL,'Desconocido','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36','2026-02-25 20:58:26'),(51,'inicio','103.196.9.138',NULL,'Desconocido','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36','2026-02-25 20:58:28'),(52,'inicio','103.196.9.138',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36','2026-02-25 20:58:29'),(53,'inicio','103.196.9.138',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36','2026-02-25 20:58:33'),(54,'inicio','154.47.30.146',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36','2026-02-25 20:58:37'),(55,'inicio','168.243.188.188',NULL,'Desconocido','WhatsApp/2.23.20.0','2026-02-25 20:58:57'),(56,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-25 20:59:12'),(57,'inicio','168.243.188.188',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 20:59:58'),(58,'inicio','168.243.188.188',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 21:01:30'),(59,'inicio','168.243.188.188',NULL,'Desconocido','WhatsApp/2.23.20.0','2026-02-25 21:01:55'),(60,'inicio','168.243.188.188',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 21:03:46'),(61,'inicio','168.243.188.188',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 21:03:52'),(62,'inicio','200.89.84.114',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 21:04:40'),(63,'inicio','200.89.84.114',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36','2026-02-25 21:05:58'),(64,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-25 21:17:17'),(65,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0','2026-02-25 21:17:47'),(66,'inicio','91.231.89.38',NULL,'Desconocido','Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:134.0) Gecko/20100101 Firefox/134.0','2026-02-25 21:19:24'),(67,'inicio','147.185.133.210',NULL,'Desconocido','Hello from Palo Alto Networks, find out more about our scans in https://docs-cortex.paloaltonetworks.com/r/1/Cortex-Xpanse/Scanning-activity','2026-02-25 21:23:18'),(68,'inicio','91.231.89.124',NULL,'Desconocido','Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:134.0) Gecko/20100101 Firefox/134.0','2026-02-25 21:25:46'),(69,'inicio','91.231.89.32',NULL,'Desconocido','Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:134.0) Gecko/20100101 Firefox/134.0','2026-02-25 21:26:56'),(70,'inicio','91.231.89.120',NULL,'Desconocido','Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:134.0) Gecko/20100101 Firefox/134.0','2026-02-25 21:27:34'),(71,'inicio','154.28.229.35',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36','2026-02-25 21:28:29'),(72,'inicio','104.164.173.42',NULL,'Desconocido','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36','2026-02-25 21:28:32'),(73,'inicio','154.28.229.35',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36','2026-02-25 21:28:34'),(74,'inicio','154.28.229.35',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36','2026-02-25 21:28:48'),(75,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-25 21:46:58'),(76,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 21:47:06'),(77,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-25 21:52:02'),(78,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-25 21:54:12'),(79,'inicio','206.188.197.142',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36','2026-02-25 21:58:29'),(80,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 21:58:31'),(81,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 21:58:33'),(82,'inicio','104.252.191.144',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36','2026-02-25 21:58:41'),(83,'inicio','103.196.9.247',NULL,'Desconocido','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36','2026-02-25 21:58:41'),(84,'inicio','104.252.191.144',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36','2026-02-25 21:58:47'),(85,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 22:01:27'),(86,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-25 22:11:40'),(87,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-25 22:18:09'),(88,'inicio','181.189.186.88',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 22:33:21'),(89,'inicio','181.189.186.88',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 22:34:36'),(90,'inicio','181.189.186.88',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 22:34:47'),(91,'inicio','181.189.186.88',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 22:36:08'),(92,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 22:40:09'),(93,'inicio','181.189.186.88',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 22:41:22'),(94,'inicio','181.189.186.88',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 22:41:52'),(95,'inicio','181.189.186.88',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 22:42:15'),(96,'inicio','181.189.186.88',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 22:44:55'),(97,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 22:49:12'),(98,'inicio','51.254.49.107',NULL,'Desconocido','Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:134.0) Gecko/20100101 Firefox/134.0','2026-02-25 22:51:21'),(99,'inicio','147.93.155.10',NULL,'Desconocido','Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1','2026-02-25 22:52:11'),(100,'inicio','181.189.186.88',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 22:56:12'),(101,'inicio','181.189.186.88',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 23:00:16'),(102,'inicio','144.217.163.247',NULL,'Desconocido','Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/126.0.6478.107 Mobile/15E148 Safari/604.1','2026-02-25 23:19:37'),(103,'inicio','181.189.186.88',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-25 23:47:27'),(104,'inicio','209.50.228.94',NULL,'Desconocido','req/v3 (https://github.com/imroc/req)','2026-02-26 00:07:18'),(105,'inicio','195.132.35.238',NULL,'Desconocido','req/v3 (https://github.com/imroc/req)','2026-02-26 00:07:18'),(106,'inicio','195.132.35.238',NULL,'Desconocido','req/v3 (https://github.com/imroc/req)','2026-02-26 00:08:41'),(107,'inicio','172.239.121.24',NULL,'Desconocido','Mozilla/5.0 (Windows NT 6.1; WOW64; rv:8.0) Gecko/20100101 Firefox/8.0','2026-02-26 00:29:37'),(108,'inicio','195.211.77.141',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36','2026-02-26 00:42:54'),(109,'inicio','195.211.77.141',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36','2026-02-26 00:43:09'),(110,'inicio','172.239.121.24',NULL,'Desconocido','Mozilla/5.0 (Windows NT 6.1; WOW64; rv:8.0) Gecko/20100101 Firefox/8.0','2026-02-26 01:04:53'),(111,'inicio','110.249.201.203',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; https://zhanzhang.toutiao.com/)','2026-02-26 02:16:21'),(112,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-26 02:50:11'),(113,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-26 02:52:53'),(114,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-26 02:54:49'),(115,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-26 03:16:53'),(116,'inicio','181.189.186.88',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 03:21:11'),(117,'inicio','181.189.186.88',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 03:23:29'),(118,'inicio','130.61.17.31',NULL,'Desconocido','wappalyzergo-scan-file/1.0','2026-02-26 03:23:31'),(119,'inicio','181.189.186.88',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 03:23:32'),(120,'inicio','181.189.186.88',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 03:24:16'),(121,'inicio','181.189.186.88',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 03:25:12'),(122,'inicio','181.189.186.88',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 03:27:01'),(123,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-26 03:33:59'),(124,'inicio','181.189.186.88',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 03:40:10'),(125,'inicio','181.189.186.88',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 03:41:16'),(126,'inicio','40.77.167.38',NULL,'Desconocido','Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm) Chrome/116.0.1938.76 Safari/537.36','2026-02-26 03:43:09'),(127,'inicio','168.243.188.87',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 03:55:56'),(128,'inicio','93.203.191.202',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36','2026-02-26 04:04:25'),(129,'inicio','190.150.230.92',NULL,'Desconocido','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Safari/605.1.15','2026-02-26 04:07:39'),(130,'inicio','190.150.230.92',NULL,'Desconocido','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Safari/605.1.15','2026-02-26 04:15:46'),(131,'inicio','190.150.230.92',NULL,'Desconocido','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Safari/605.1.15','2026-02-26 04:16:07'),(132,'inicio','181.189.186.88',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 04:29:05'),(133,'inicio','182.10.225.113',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36','2026-02-26 04:50:35'),(134,'inicio','54.218.252.1',NULL,'Desconocido','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36','2026-02-26 05:14:01'),(135,'inicio','162.216.149.154',NULL,'Desconocido','Hello from Palo Alto Networks, find out more about our scans in https://docs-cortex.paloaltonetworks.com/r/1/Cortex-Xpanse/Scanning-activity','2026-02-26 05:47:20'),(136,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-26 05:50:19'),(137,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 05:50:25'),(138,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 05:50:34'),(139,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-26 05:50:39'),(140,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-26 05:50:43'),(141,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-26 05:50:46'),(142,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-26 05:51:00'),(143,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-26 05:51:11'),(144,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-26 05:56:07'),(145,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0','2026-02-26 06:11:09'),(146,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-26 06:12:23'),(147,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-26 06:17:00'),(148,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-26 06:17:40'),(149,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-26 06:19:20'),(150,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-26 06:23:11'),(151,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-26 06:28:47'),(152,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-26 06:32:33'),(153,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-26 06:33:02'),(154,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 06:44:46'),(155,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 16; 24117RN76L Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.103 Mobile Safari/537.36 Instagram 417.0.0.54.77 Android (36/16; 450dpi; 1080x2400; Xiaomi/Redmi; 24117RN76L; tan','2026-02-26 06:47:44'),(156,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 16; 24117RN76L Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.103 Mobile Safari/537.36 Instagram 417.0.0.54.77 Android (36/16; 450dpi; 1080x2400; Xiaomi/Redmi; 24117RN76L; tan','2026-02-26 06:47:59'),(157,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 06:49:33'),(158,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 06:50:22'),(159,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 06:50:24'),(160,'inicio','66.249.68.70',NULL,'Desconocido','Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)','2026-02-26 07:57:45'),(161,'inicio','157.230.119.216',NULL,'Desconocido','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36','2026-02-26 08:03:55'),(162,'inicio','165.227.197.8',NULL,'Desconocido','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36','2026-02-26 08:04:23'),(163,'inicio','101.99.92.125',NULL,'Desconocido','Mozilla/5.0 (Ubuntu; Linux i686) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36','2026-02-26 08:50:37'),(164,'inicio','52.57.46.129',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Mobile Safari/537.36','2026-02-26 09:18:36'),(165,'inicio','154.30.105.232',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246','2026-02-26 09:18:39'),(166,'inicio','18.158.213.240',NULL,'Desconocido','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36','2026-02-26 09:22:01'),(167,'inicio','154.30.105.231',NULL,'Desconocido','Mozilla/5.0 (X11; U; Linux x86_64; en-GB; rv:116.0) Gecko/20061209 Firefox/116.0','2026-02-26 09:22:05'),(168,'inicio','138.68.138.242',NULL,'Desconocido','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36','2026-02-26 09:35:41'),(169,'inicio','154.223.217.169',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0','2026-02-26 09:54:28'),(170,'inicio','136.109.50.119',NULL,'Desconocido','Mozilla/5.0 (compatible; CMS-Checker/1.0; +https://example.com)','2026-02-26 09:55:25'),(171,'inicio','34.148.2.57',NULL,'Desconocido','Mozilla/5.0 (compatible; CMS-Checker/1.0; +https://example.com)','2026-02-26 09:56:06'),(172,'inicio','185.12.250.104',NULL,'Desconocido','Mozilla/5.0 (iPhone; CPU iPhone OS 17_3_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.3.1 Mobile/15E148 Safari/604','2026-02-26 10:07:14'),(173,'inicio','133.242.174.119',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36','2026-02-26 10:38:26'),(174,'inicio','34.68.160.63',NULL,'Desconocido','Mozilla/5.0 (compatible; CMS-Checker/1.0; +https://example.com)','2026-02-26 11:44:45'),(175,'inicio','34.73.36.208',NULL,'Desconocido','Mozilla/5.0 (compatible; CMS-Checker/1.0; +https://example.com)','2026-02-26 11:51:36'),(176,'inicio','18.202.217.12',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36','2026-02-26 12:38:29'),(177,'inicio','178.156.208.254',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.5060.134 Safari/537.36','2026-02-26 13:02:43'),(178,'inicio','5.133.192.128',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36','2026-02-26 13:17:37'),(179,'inicio','161.35.181.201',NULL,'Desconocido','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36','2026-02-26 13:18:58'),(180,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 14:38:35'),(181,'inicio','136.115.153.157',NULL,'Desconocido','Mozilla/5.0 (compatible; CMS-Checker/1.0; +https://example.com)','2026-02-26 15:06:39'),(182,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 15:16:20'),(183,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 15:16:25'),(184,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 15:19:14'),(185,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 15:19:47'),(186,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 15:19:49'),(187,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 15:19:51'),(188,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 15:19:52'),(189,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 15:20:08'),(190,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 15:20:09'),(191,'inicio','35.222.47.192',NULL,'Desconocido','Mozilla/5.0 (compatible; CMS-Checker/1.0; +https://example.com)','2026-02-26 15:25:09'),(192,'inicio','190.86.75.51',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 16:00:52'),(193,'inicio','190.99.43.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 16:04:36'),(194,'inicio','190.99.43.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 16:04:47'),(195,'inicio','173.244.32.16',NULL,'Desconocido','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36','2026-02-26 16:44:57'),(196,'inicio','188.126.79.29',NULL,'Desconocido','Go-http-client/1.1','2026-02-26 16:45:03'),(197,'inicio','200.89.84.130',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 17:11:11'),(198,'inicio','181.189.186.13',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 17:41:11'),(199,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 17:42:07'),(200,'inicio','181.189.186.13',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 17:42:16'),(201,'inicio','181.189.186.13',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 17:42:24'),(202,'inicio','31.13.115.9',NULL,'Desconocido','meta-externalagent/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler)','2026-02-26 17:53:26'),(203,'inicio','190.99.43.26',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0','2026-02-26 19:39:35'),(204,'inicio','190.99.43.26',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0','2026-02-26 19:40:42'),(205,'inicio','181.189.186.13',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-26 20:17:23'),(206,'inicio','190.99.43.26',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-26 20:44:55'),(207,'inicio','190.150.106.51',NULL,'Desconocido','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-26 21:04:32'),(208,'inicio','34.228.151.51',NULL,'Desconocido','Mozilla/5.0 (compatible; Yahoo Link Preview; https://help.yahoo.com/kb/mail/yahoo-link-preview-SLN23615.html)','2026-02-26 21:34:30'),(209,'inicio','35.203.210.194',NULL,'Desconocido','Hello from Palo Alto Networks, find out more about our scans in https://docs-cortex.paloaltonetworks.com/r/1/Cortex-Xpanse/Scanning-activity','2026-02-26 23:45:35'),(210,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 00:58:53'),(211,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 00:59:26'),(212,'inicio','35.203.211.51',NULL,'Desconocido','Hello from Palo Alto Networks, find out more about our scans in https://docs-cortex.paloaltonetworks.com/r/1/Cortex-Xpanse/Scanning-activity','2026-02-27 01:04:47'),(213,'inicio','104.196.174.201',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 12; Redmi Note 11) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.6312.112 Mobile Safari/537.36','2026-02-27 01:10:39'),(214,'inicio','110.249.201.114',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; https://zhanzhang.toutiao.com/)','2026-02-27 02:46:14'),(215,'inicio','147.185.132.36',NULL,'Desconocido','Hello from Palo Alto Networks, find out more about our scans in https://docs-cortex.paloaltonetworks.com/r/1/Cortex-Xpanse/Scanning-activity','2026-02-27 03:16:14'),(216,'inicio','66.249.75.69',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.7559.132 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)','2026-02-27 04:04:02'),(217,'inicio','66.249.75.69',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.84 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)','2026-02-27 04:05:33'),(218,'inicio','66.249.75.68',NULL,'Desconocido','Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)','2026-02-27 04:05:33'),(219,'inicio','66.249.75.70',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.7559.132 Mobile Safari/537.36 (compatible; Google-InspectionTool/1.0;)','2026-02-27 04:09:24'),(220,'inicio','66.249.75.68',NULL,'Desconocido','Mozilla/5.0 (compatible; Google-InspectionTool/1.0;)','2026-02-27 04:09:24'),(221,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 04:10:34'),(222,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 04:10:51'),(223,'inicio','192.36.109.98',NULL,'Desconocido','Mozilla/5.0 (iPhone; CPU iPhone OS 17_3_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.3.1 Mobile/15E148 Safari/604','2026-02-27 04:17:10'),(224,'inicio','66.249.75.68',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.7559.132 Mobile Safari/537.36 (compatible; GoogleOther)','2026-02-27 04:18:50'),(225,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 04:28:01'),(226,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 04:29:06'),(227,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 04:29:35'),(228,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 04:41:20'),(229,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 04:41:30'),(230,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-27 04:41:46'),(231,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-27 04:41:57'),(232,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 04:41:59'),(233,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 04:46:45'),(234,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 04:56:16'),(235,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 04:56:28'),(236,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 05:13:11'),(237,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 05:13:18'),(238,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 05:29:28'),(239,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-27 05:35:07'),(240,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 05:54:44'),(241,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 05:55:01'),(242,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-27 05:55:10'),(243,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 06:02:42'),(244,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-27 06:03:56'),(245,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-27 06:05:42'),(246,'inicio','104.252.191.183',NULL,'Desconocido','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36','2026-02-27 07:39:05'),(247,'inicio','104.164.126.40',NULL,'Desconocido','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36','2026-02-27 07:39:15'),(248,'inicio','104.164.126.40',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36','2026-02-27 07:39:36'),(249,'inicio','104.164.173.226',NULL,'Desconocido','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36','2026-02-27 07:45:35'),(250,'inicio','104.164.126.18',NULL,'Desconocido','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36','2026-02-27 07:45:36'),(251,'inicio','104.164.126.18',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36','2026-02-27 07:45:57'),(252,'inicio','103.196.9.38',NULL,'Desconocido','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36','2026-02-27 07:51:48'),(253,'inicio','103.4.251.98',NULL,'Desconocido','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36','2026-02-27 07:51:48'),(254,'inicio','103.196.9.38',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36','2026-02-27 07:52:04'),(255,'inicio','179.125.154.189',NULL,'Desconocido','Mozilla/5.0 (Windows NT 5.1; rv:9.0.1) Gecko/20100101 Firefox/9.0.1','2026-02-27 08:50:21'),(256,'inicio','59.127.229.109',NULL,'Desconocido','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/601.7.7 (KHTML, like Gecko) Version/9.1.2 Safari/601.7.7','2026-02-27 08:53:38'),(257,'inicio','34.178.57.39',NULL,'Desconocido','Mozilla/5.0 (compatible; CMS-Checker/1.0; +https://example.com)','2026-02-27 09:21:28'),(258,'inicio','157.55.39.10',NULL,'Desconocido','Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm) Chrome/116.0.1938.76 Safari/537.36','2026-02-27 11:07:53'),(259,'inicio','40.77.177.215',NULL,'Desconocido','Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm) Chrome/136.0.0.0 Safari/537.36','2026-02-27 11:24:10'),(260,'inicio','47.254.91.83',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36 Edg/134.0.0.0','2026-02-27 11:41:02'),(261,'inicio','47.254.91.83',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36 Edg/134.0.0.0','2026-02-27 11:41:05'),(262,'inicio','47.254.91.83',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36 Edg/134.0.0.0','2026-02-27 11:41:05'),(263,'inicio','47.254.91.83',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36 Edg/134.0.0.0','2026-02-27 11:41:06'),(264,'inicio','47.254.91.83',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36 Edg/134.0.0.0','2026-02-27 11:41:06'),(265,'inicio','47.254.91.83',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36 Edg/134.0.0.0','2026-02-27 11:41:06'),(266,'inicio','47.254.91.83',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36 Edg/134.0.0.0','2026-02-27 11:41:06'),(267,'inicio','47.254.91.83',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36 Edg/134.0.0.0','2026-02-27 11:41:08'),(268,'inicio','173.252.87.6',NULL,'Desconocido','facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)','2026-02-27 12:48:32'),(269,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-27 14:41:14'),(270,'inicio','205.210.31.156',NULL,'Desconocido','Hello from Palo Alto Networks, find out more about our scans in https://docs-cortex.paloaltonetworks.com/r/1/Cortex-Xpanse/Scanning-activity','2026-02-27 15:50:09'),(271,'inicio','34.45.130.203',NULL,'Desconocido','Mozilla/5.0 (compatible; CMS-Checker/1.0; +https://example.com)','2026-02-27 16:07:41'),(272,'inicio','34.56.174.51',NULL,'Desconocido','Mozilla/5.0 (compatible; CMS-Checker/1.0; +https://example.com)','2026-02-27 16:13:02'),(273,'inicio','34.219.146.255',NULL,'Desconocido','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36','2026-02-27 16:30:04'),(274,'inicio','127.0.0.1',NULL,'Desconocido','curl/8.7.1','2026-02-27 17:22:08'),(275,'inicio','127.0.0.1',NULL,'Desconocido','curl/8.7.1','2026-02-27 17:22:41'),(276,'inicio','127.0.0.1',NULL,'Desconocido','curl/8.7.1','2026-02-27 17:22:43'),(277,'inicio','127.0.0.1',NULL,'Desconocido','curl/8.7.1','2026-02-27 17:22:46'),(278,'inicio','127.0.0.1',NULL,'Desconocido','curl/8.7.1','2026-02-27 17:22:48'),(279,'inicio','127.0.0.1',NULL,'Desconocido','curl/8.7.1','2026-02-27 17:22:50'),(280,'inicio','127.0.0.1',NULL,'Desconocido','curl/8.7.1','2026-02-27 17:22:52'),(281,'inicio','127.0.0.1',NULL,'Desconocido','curl/8.7.1','2026-02-27 17:23:37'),(282,'inicio','45.228.233.235',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 17:51:59'),(283,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 18:57:32'),(284,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-27 18:57:39'),(285,'inicio','200.85.30.86',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-27 19:58:12'),(286,'inicio','200.85.30.86',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36','2026-02-27 19:58:16'),(287,'inicio','54.173.122.192',NULL,'Desconocido','Go-http-client/1.1','2026-02-27 21:04:03'),(288,'inicio','193.227.109.29',NULL,'Desconocido','ivre-masscan/1.3 https://github.com/robertdavidgraham/','2026-02-27 21:19:48'),(289,'inicio','121.127.34.166',NULL,'Desconocido','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36','2026-02-27 21:23:28'),(290,'inicio','216.180.246.141',NULL,'Desconocido','Mozilla/5.0 (compatible; GenomeCrawlerd/1.0; +https://www.nokia.com/genomecrawler)','2026-02-27 21:52:22'),(291,'inicio','216.180.246.141',NULL,'Desconocido','Mozilla/5.0 (compatible; GenomeCrawlerd/1.0; +https://www.nokia.com/genomecrawler)','2026-02-27 21:53:31'),(292,'inicio','216.180.246.141',NULL,'Desconocido','Mozilla/5.0 (compatible; GenomeCrawlerd/1.0; +https://www.nokia.com/genomecrawler)','2026-02-27 21:53:50'),(293,'inicio','216.180.246.141',NULL,'Desconocido','Mozilla/5.0 (compatible; GenomeCrawlerd/1.0; +https://www.nokia.com/genomecrawler)','2026-02-27 22:08:39'),(294,'inicio','162.216.149.107',NULL,'Desconocido','Hello from Palo Alto Networks, find out more about our scans in https://docs-cortex.paloaltonetworks.com/r/1/Cortex-Xpanse/Scanning-activity','2026-02-27 22:15:20'),(295,'inicio','162.216.150.184',NULL,'Desconocido','Hello from Palo Alto Networks, find out more about our scans in https://docs-cortex.paloaltonetworks.com/r/1/Cortex-Xpanse/Scanning-activity','2026-02-27 23:01:23'),(296,'inicio','121.127.34.166',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0','2026-02-27 23:07:34'),(297,'inicio','34.94.13.115',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 14; OnePlus 12) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.6422.102 Mobile Safari/537.36','2026-02-27 23:55:28'),(298,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-28 01:27:29'),(299,'inicio','151.115.100.41',NULL,'Desconocido','curl/7.81.0','2026-02-28 02:08:48'),(300,'inicio','151.115.100.41',NULL,'Desconocido','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.3','2026-02-28 02:08:51'),(301,'inicio','111.225.148.217',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; https://zhanzhang.toutiao.com/)','2026-02-28 02:16:54'),(302,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-28 02:38:59'),(303,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-28 03:12:25'),(304,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-28 03:16:04'),(305,'inicio','85.11.167.4',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36','2026-02-28 03:20:18'),(306,'inicio','85.11.167.4',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36','2026-02-28 03:20:20'),(307,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-28 03:23:59'),(308,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-28 03:25:25'),(309,'inicio','52.167.144.215',NULL,'Desconocido','Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm) Chrome/116.0.1938.76 Safari/537.36','2026-02-28 03:26:35'),(310,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-28 03:27:52'),(311,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-28 03:28:13'),(312,'inicio','66.249.75.68',NULL,'Desconocido','Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)','2026-02-28 03:42:04'),(313,'inicio','66.249.75.70',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.7559.132 Mobile Safari/537.36 (compatible; GoogleOther)','2026-02-28 03:43:23'),(314,'inicio','66.249.75.68',NULL,'Desconocido','Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.7559.132 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)','2026-02-28 03:44:56'),(315,'inicio','198.244.226.181',NULL,'Desconocido','Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)','2026-02-28 03:48:30'),(316,'inicio','127.0.0.1',NULL,'Desconocido','curl/8.7.1','2026-02-28 04:03:28'),(317,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-28 04:05:30'),(318,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-28 04:05:55'),(319,'inicio','127.0.0.1',NULL,'Desconocido','curl/8.7.1','2026-02-28 04:07:01'),(320,'inicio','127.0.0.1',NULL,'Desconocido','curl/8.7.1','2026-02-28 04:07:04'),(321,'inicio','127.0.0.1',NULL,'Desconocido','curl/8.7.1','2026-02-28 04:07:06'),(322,'inicio','127.0.0.1',NULL,'Desconocido','curl/8.7.1','2026-02-28 04:07:08'),(323,'inicio','127.0.0.1',NULL,'Desconocido','curl/8.7.1','2026-02-28 04:07:10'),(324,'inicio','127.0.0.1',NULL,'Desconocido','curl/8.7.1','2026-02-28 04:07:13'),(325,'inicio','127.0.0.1',NULL,'Desconocido','curl/8.7.1','2026-02-28 04:11:37'),(326,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-28 04:15:17'),(327,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-28 04:15:27'),(328,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-28 04:35:26'),(329,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-28 04:40:35'),(330,'inicio','190.150.121.76',NULL,'Desconocido','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-28 04:40:47');
/*!40000 ALTER TABLE `visitas_sitio` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'sietelsa'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-28  4:42:19

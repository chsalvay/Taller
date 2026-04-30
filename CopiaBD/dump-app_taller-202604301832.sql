-- MySQL dump 10.13  Distrib 8.0.19, for Win64 (x86_64)
--
-- Host: localhost    Database: app_taller
-- ------------------------------------------------------
-- Server version	8.4.8

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
-- Table structure for table `categorias`
--

DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias` (
  `id_categoria` int NOT NULL AUTO_INCREMENT,
  `nombre_categoria` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_categoria`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categorias`
--

LOCK TABLES `categorias` WRITE;
/*!40000 ALTER TABLE `categorias` DISABLE KEYS */;
INSERT INTO `categorias` VALUES (1,'Filtros',1),(2,'Frenos',1),(3,'Encendido',1),(4,'Suspensión',1),(5,'Transmisión',1),(6,'Motor',1),(7,'Eléctrico',1),(8,'Escape',1);
/*!40000 ALTER TABLE `categorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clientes`
--

DROP TABLE IF EXISTS `clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clientes` (
  `id_cliente` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_vehiculo_marca` int DEFAULT NULL,
  `id_modelo` int DEFAULT NULL,
  `patente` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_cliente`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clientes`
--

LOCK TABLES `clientes` WRITE;
/*!40000 ALTER TABLE `clientes` DISABLE KEYS */;
INSERT INTO `clientes` VALUES (1,'Christian Salvay','11 de septimbre 1447, Banfield','11 5331 8385',2,11,'CCO561',1,'2026-04-20 13:44:55','2026-04-20 13:44:55'),(2,'Christian Salvay','11 de septimbre 1447, Banfield','11 5331 8385',6,12,'AB456VK',1,'2026-04-20 13:46:01','2026-04-20 13:46:01');
/*!40000 ALTER TABLE `clientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compatibilidad_vehiculos`
--

DROP TABLE IF EXISTS `compatibilidad_vehiculos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `compatibilidad_vehiculos` (
  `id_compatibilidad` int NOT NULL AUTO_INCREMENT,
  `id_repuesto` int NOT NULL,
  `id_modelo` int NOT NULL,
  `id_motorizacion` int DEFAULT NULL,
  `anio_inicio` int DEFAULT NULL,
  `anio_fin` int DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_compatibilidad`),
  KEY `fk_compat_repuesto` (`id_repuesto`),
  KEY `fk_compat_modelo` (`id_modelo`),
  KEY `fk_compat_motor` (`id_motorizacion`),
  CONSTRAINT `fk_compat_modelo` FOREIGN KEY (`id_modelo`) REFERENCES `vehiculos_modelos` (`id_modelo`),
  CONSTRAINT `fk_compat_motor` FOREIGN KEY (`id_motorizacion`) REFERENCES `motorizaciones` (`id_motorizacion`),
  CONSTRAINT `fk_compat_repuesto` FOREIGN KEY (`id_repuesto`) REFERENCES `repuestos` (`id_repuesto`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compatibilidad_vehiculos`
--

LOCK TABLES `compatibilidad_vehiculos` WRITE;
/*!40000 ALTER TABLE `compatibilidad_vehiculos` DISABLE KEYS */;
INSERT INTO `compatibilidad_vehiculos` VALUES (1,1,11,1,NULL,NULL,1),(2,2,11,1,NULL,NULL,1),(3,3,11,1,NULL,NULL,1),(4,4,13,1,NULL,NULL,1),(5,5,13,1,NULL,NULL,1),(6,6,12,1,NULL,NULL,1),(7,7,12,1,NULL,NULL,1),(8,8,13,1,NULL,NULL,1),(9,9,13,1,NULL,NULL,1),(10,10,1,1,2017,2022,1),(11,11,7,1,NULL,NULL,1);
/*!40000 ALTER TABLE `compatibilidad_vehiculos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `marcas`
--

DROP TABLE IF EXISTS `marcas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marcas` (
  `id_marca` int NOT NULL AUTO_INCREMENT,
  `nombre_marca` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_marca`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `marcas`
--

LOCK TABLES `marcas` WRITE;
/*!40000 ALTER TABLE `marcas` DISABLE KEYS */;
INSERT INTO `marcas` VALUES (1,'Bosch',1),(2,'Valeo',1),(3,'SKF',1),(4,'Brembo',1),(5,'Monroe',1),(6,'Fric-Rot',1),(7,'NGK',1),(8,'Filtros Fram',1),(9,'Gates',1),(10,'Magneti Marelli',1),(11,'Luk',1),(12,'Sachs',1),(13,'Mahle',1),(14,'TotalEnergies',1),(15,'Castrol',1);
/*!40000 ALTER TABLE `marcas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `motorizaciones`
--

DROP TABLE IF EXISTS `motorizaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `motorizaciones` (
  `id_motorizacion` int NOT NULL AUTO_INCREMENT,
  `nombre_motor` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_motorizacion`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `motorizaciones`
--

LOCK TABLES `motorizaciones` WRITE;
/*!40000 ALTER TABLE `motorizaciones` DISABLE KEYS */;
INSERT INTO `motorizaciones` VALUES (1,'1.6 Nafta','Motor naftero 1.6',1),(2,'1.8 Nafta','Motor naftero 1.8',1),(3,'2.0 Nafta','Motor naftero 2.0',1),(4,'2.0 Diesel','Motor diesel 2.0',1),(5,'2.4 Diesel','Motor diesel 2.4',1),(6,'1.4 Turbo','Motor turbo 1.4',1),(7,'1.6 Turbo','Motor turbo 1.6',1);
/*!40000 ALTER TABLE `motorizaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ordenes_trabajo`
--

DROP TABLE IF EXISTS `ordenes_trabajo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ordenes_trabajo` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int DEFAULT NULL,
  `cliente` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vehiculo` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `patente` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `estado` enum('abierta','en_progreso','cerrada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'abierta',
  `fecha_ot` date NOT NULL,
  `fecha_finalizacion` date DEFAULT NULL,
  `monto_total` decimal(12,2) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ordenes_trabajo`
--

LOCK TABLES `ordenes_trabajo` WRITE;
/*!40000 ALTER TABLE `ordenes_trabajo` DISABLE KEYS */;
INSERT INTO `ordenes_trabajo` VALUES (5,1,'Christian Salvay','Ford Escort','CCO561','cambio de kit de distribución | cambio de aceite y filtro','cerrada','2026-04-20','2026-04-20',405000.00,'2026-04-20 18:55:41','2026-04-20 18:56:33'),(6,1,'Christian Salvay','Ford Escort','CCO561','cambio de pastillas de freno','cerrada','2026-04-20','2026-04-20',49000.00,'2026-04-20 18:57:20','2026-04-20 18:59:24'),(7,2,'Christian Salvay','Peugeot 2008','AB456VK','Cambio de aceite y filtros','cerrada','2026-04-20','2026-04-21',84000.00,'2026-04-20 19:12:05','2026-04-21 23:26:16'),(8,NULL,'Cliente Demo','Toyota Corolla','AA123BB','Cambio de aceite y filtros','abierta','2026-04-21',NULL,NULL,'2026-04-21 22:31:13','2026-04-21 22:31:13'),(9,1,'Christian Salvay','Ford Escort','CCO561','cambio de aceite | cambiar agua radiador | cambio de pastillas de freno','cerrada','2026-04-21','2026-04-21',158200.00,'2026-04-21 23:19:07','2026-04-21 23:24:59'),(10,1,'Christian Salvay','Ford Escort','CCO561','cambio de aceite y filtros | cambio de filtro de aire | cambio de agua del radiador','abierta','2026-04-27',NULL,NULL,'2026-04-27 13:00:53','2026-04-27 13:10:40');
/*!40000 ALTER TABLE `ordenes_trabajo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ordenes_trabajo_detalle`
--

DROP TABLE IF EXISTS `ordenes_trabajo_detalle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ordenes_trabajo_detalle` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_orden` int unsigned NOT NULL,
  `id_repuesto` int DEFAULT NULL,
  `descripcion_libre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cantidad` int unsigned NOT NULL DEFAULT '1',
  `precio_final` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_otd_orden` (`id_orden`),
  KEY `fk_otd_repuesto` (`id_repuesto`),
  CONSTRAINT `fk_otd_orden` FOREIGN KEY (`id_orden`) REFERENCES `ordenes_trabajo` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_otd_repuesto` FOREIGN KEY (`id_repuesto`) REFERENCES `repuestos` (`id_repuesto`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ordenes_trabajo_detalle`
--

LOCK TABLES `ordenes_trabajo_detalle` WRITE;
/*!40000 ALTER TABLE `ordenes_trabajo_detalle` DISABLE KEYS */;
INSERT INTO `ordenes_trabajo_detalle` VALUES (29,5,3,NULL,1,25000.00),(30,5,4,NULL,1,120000.00),(31,5,1,NULL,1,200000.00),(32,5,NULL,'cambio de aire en las ruedas',1,20000.00),(33,5,NULL,'cambio de kit de distribución',1,30000.00),(34,5,NULL,'cambio de aceite y filtro',1,10000.00),(35,6,5,NULL,2,12000.00),(36,6,NULL,'cambio de pastillas de freno',1,25000.00),(50,9,3,NULL,2,30000.00),(51,9,8,NULL,2,2000.00),(52,9,5,NULL,1,12000.00),(53,9,9,NULL,4,2000.00),(54,9,2,NULL,1,20000.00),(55,9,NULL,'Ficha de esteror',1,1200.00),(56,9,NULL,'cambio de laparita',1,20000.00),(57,9,NULL,'cambio de aceite',1,3000.00),(58,9,NULL,'cambiar agua radiador',1,0.00),(59,9,NULL,'cambio de pastillas de freno',1,30000.00),(60,7,6,NULL,6,10000.00),(61,7,8,NULL,6,2000.00),(62,7,7,NULL,1,4000.00),(63,7,9,NULL,4,2000.00),(64,7,NULL,'Cambio de aceite y filtros',1,0.00),(77,10,3,NULL,1,NULL),(78,10,4,NULL,1,NULL),(79,10,NULL,'Agua del radiador',1,NULL);
/*!40000 ALTER TABLE `ordenes_trabajo_detalle` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `presupuesto`
--

DROP TABLE IF EXISTS `presupuesto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `presupuesto` (
  `id_presupuesto` int NOT NULL AUTO_INCREMENT,
  `numero_presupuesto` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha` date NOT NULL,
  `cliente` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `monto_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `activo` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_presupuesto`),
  UNIQUE KEY `numero_presupuesto` (`numero_presupuesto`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `presupuesto`
--

LOCK TABLES `presupuesto` WRITE;
/*!40000 ALTER TABLE `presupuesto` DISABLE KEYS */;
INSERT INTO `presupuesto` VALUES (1,'1','2026-04-27','Christian Salvay',152000.00,1,'2026-04-27 18:08:20','2026-04-27 18:34:32'),(2,'2','2026-04-27','Alberto',230000.00,1,'2026-04-27 18:22:50','2026-04-27 18:22:50'),(3,'3','2026-04-27','Rodrigo',75500.00,1,'2026-04-27 18:59:55','2026-04-27 19:03:19'),(4,'4','2026-04-27','beto',13200.00,1,'2026-04-27 19:41:10','2026-04-27 19:41:10'),(5,'5','2026-04-27','Toto',26400.00,1,'2026-04-27 19:42:34','2026-04-27 19:42:34'),(6,'6','2026-04-27','Felipe',26400.00,1,'2026-04-27 19:46:23','2026-04-27 19:46:23'),(7,'7','2026-04-27','Felipe',26400.00,1,'2026-04-27 19:46:34','2026-04-27 19:46:34'),(8,'8','2026-04-27','Frank',13200.00,1,'2026-04-27 19:49:56','2026-04-27 19:49:56'),(9,'9','2026-04-27','Pedro',26400.00,1,'2026-04-27 19:50:57','2026-04-27 19:50:57'),(10,'10','2026-04-27','Eric',26000.00,1,'2026-04-27 21:14:01','2026-04-27 21:14:01'),(11,'11','2026-04-27','toto',39600.00,1,'2026-04-27 21:15:23','2026-04-27 21:15:56');
/*!40000 ALTER TABLE `presupuesto` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `presupuesto_detalle`
--

DROP TABLE IF EXISTS `presupuesto_detalle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `presupuesto_detalle` (
  `id_detalle` int NOT NULL AUTO_INCREMENT,
  `id_presupuesto` int NOT NULL,
  `id_repuesto` int DEFAULT NULL,
  `material` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` int NOT NULL DEFAULT '1',
  `precio_costo` decimal(10,2) NOT NULL DEFAULT '0.00',
  `precio_venta` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id_detalle`),
  KEY `fk_presupuesto_detalle_presupuesto` (`id_presupuesto`),
  KEY `fk_presupuesto_detalle_repuesto` (`id_repuesto`),
  CONSTRAINT `fk_presupuesto_detalle_presupuesto` FOREIGN KEY (`id_presupuesto`) REFERENCES `presupuesto` (`id_presupuesto`) ON DELETE CASCADE,
  CONSTRAINT `fk_presupuesto_detalle_repuesto` FOREIGN KEY (`id_repuesto`) REFERENCES `repuestos` (`id_repuesto`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `presupuesto_detalle`
--

LOCK TABLES `presupuesto_detalle` WRITE;
/*!40000 ALTER TABLE `presupuesto_detalle` DISABLE KEYS */;
INSERT INTO `presupuesto_detalle` VALUES (2,2,1,'Kit distribución',1,200000.00,230000.00),(3,1,4,'Aceite 10W-40 (4 LTS)',1,120000.00,130000.00),(4,1,NULL,'agua para radiador',1,20000.00,22000.00),(9,3,11,'Reten de válvula',1,20000.00,20000.00),(10,3,NULL,'Agua de radiador',1,0.00,30000.00),(11,3,11,'Reten de válvula',1,20000.00,25500.00),(12,4,5,'Pastillas de freno WA20-7',1,12000.00,13200.00),(13,5,5,'Pastillas de freno WA20-7',2,12000.00,13200.00),(14,6,5,'Pastillas de freno WA20-7',2,12000.00,13200.00),(16,7,5,'Pastillas de freno WA20-7',2,12000.00,13200.00),(17,8,5,'Pastillas de freno WA20-7',1,12000.00,13200.00),(21,9,5,'Pastillas de freno WA20-7',2,12000.00,13200.00),(22,10,3,'Filtro de aire',1,25000.00,25800.00),(23,10,NULL,'sasa',1,0.00,200.00),(26,11,5,'Pastillas de freno WA20-7',3,12000.00,13200.00);
/*!40000 ALTER TABLE `presupuesto_detalle` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proveedores`
--

DROP TABLE IF EXISTS `proveedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proveedores` (
  `id_proveedor` int NOT NULL AUTO_INCREMENT,
  `razon_social` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cuit` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_proveedor`),
  UNIQUE KEY `cuit` (`cuit`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proveedores`
--

LOCK TABLES `proveedores` WRITE;
/*!40000 ALTER TABLE `proveedores` DISABLE KEYS */;
INSERT INTO `proveedores` VALUES (1,'Repuestos Centro','30-12345678-9',1),(2,'AutoPartes Sur','30-98765432-1',1),(3,'Distribuidora Norte','30-11223344-5',1);
/*!40000 ALTER TABLE `proveedores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `repuestos`
--

DROP TABLE IF EXISTS `repuestos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `repuestos` (
  `id_repuesto` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_marca` int DEFAULT NULL,
  `id_categoria` int DEFAULT NULL,
  `id_unidad` int DEFAULT NULL,
  `id_ubicacion` int DEFAULT NULL,
  `id_proveedor` int DEFAULT NULL,
  `stock_actual` int DEFAULT '0',
  `stock_minimo` int DEFAULT '5',
  `precio_costo` decimal(10,2) DEFAULT NULL,
  `precio_venta` decimal(10,2) DEFAULT NULL,
  `fecha_ingreso` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_repuesto`),
  UNIQUE KEY `sku` (`codigo`),
  KEY `fk_repuestos_marca` (`id_marca`),
  KEY `fk_repuestos_categoria` (`id_categoria`),
  KEY `fk_repuestos_unidad` (`id_unidad`),
  KEY `fk_repuestos_proveedor` (`id_proveedor`),
  CONSTRAINT `fk_repuestos_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`),
  CONSTRAINT `fk_repuestos_marca` FOREIGN KEY (`id_marca`) REFERENCES `marcas` (`id_marca`),
  CONSTRAINT `fk_repuestos_proveedor` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`),
  CONSTRAINT `fk_repuestos_unidad` FOREIGN KEY (`id_unidad`) REFERENCES `unidades` (`id_unidad`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `repuestos`
--

LOCK TABLES `repuestos` WRITE;
/*!40000 ALTER TABLE `repuestos` DISABLE KEYS */;
INSERT INTO `repuestos` VALUES (1,'1','Kit distribución',6,6,1,NULL,2,20,5,200000.00,206000.00,'2026-04-20 14:30:54',1),(2,'2','Manguera de agua',10,6,1,NULL,2,9,5,20000.00,20600.00,'2026-04-20 17:12:44',1),(3,'3','Filtro de aire',8,1,1,NULL,2,22,11,25000.00,25750.00,'2026-04-20 17:18:43',1),(4,'4','Aceite 10W-40 (4 LTS)',15,6,1,NULL,2,3,2,120000.00,123600.00,'2026-04-20 18:48:44',1),(5,'5','Pastillas de freno WA20-7',1,2,1,NULL,2,2,1,12000.00,12360.00,'2026-04-20 18:58:30',1),(6,'6','Filtro de aire',8,1,1,NULL,2,6,5,10000.00,10300.00,'2026-04-20 19:13:12',1),(7,'7','Filtro de nafta',8,1,1,NULL,2,9,5,4000.00,4120.00,'2026-04-20 19:14:04',1),(8,'8','Filtro de ambiente',8,1,1,NULL,2,7,5,2000.00,2000.00,'2026-04-20 19:15:09',1),(9,'9','Aceite 10W-40 (80 LTS)',15,6,3,NULL,2,72,5,2000.00,2060.00,'2026-04-20 19:17:27',1),(10,'FIL-001','Filtro de aceite Corolla',1,1,1,NULL,1,12,3,18500.00,19055.00,'2026-04-21 22:31:13',1),(11,'10','Reten de válvula',15,6,1,NULL,3,6,5,20000.00,20000.00,'2026-04-21 23:08:21',1);
/*!40000 ALTER TABLE `repuestos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Admin','2026-04-09 17:22:03');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `unidades`
--

DROP TABLE IF EXISTS `unidades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `unidades` (
  `id_unidad` int NOT NULL AUTO_INCREMENT,
  `nombre_unidad` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abreviatura` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_unidad`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `unidades`
--

LOCK TABLES `unidades` WRITE;
/*!40000 ALTER TABLE `unidades` DISABLE KEYS */;
INSERT INTO `unidades` VALUES (1,'Unidad','u',1),(2,'Caja','cj',1),(3,'Litro','l',1),(4,'Par','par',1),(5,'Juego','jgo',1);
/*!40000 ALTER TABLE `unidades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol_id` tinyint unsigned NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `fk_usuarios_roles` (`rol_id`),
  CONSTRAINT `fk_usuarios_roles` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'Admin','123456',1,1,'2026-04-09 17:22:03','2026-04-09 17:22:03');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehiculos_marcas`
--

DROP TABLE IF EXISTS `vehiculos_marcas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehiculos_marcas` (
  `id_vehiculo_marca` int NOT NULL AUTO_INCREMENT,
  `nombre_marca_v` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_vehiculo_marca`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehiculos_marcas`
--

LOCK TABLES `vehiculos_marcas` WRITE;
/*!40000 ALTER TABLE `vehiculos_marcas` DISABLE KEYS */;
INSERT INTO `vehiculos_marcas` VALUES (1,'Toyota',1),(2,'Ford',1),(3,'Volkswagen',1),(4,'Chevrolet',1),(5,'Renault',1),(6,'Peugeot',1),(7,'Fiat',1),(8,'Multimarca',1);
/*!40000 ALTER TABLE `vehiculos_marcas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehiculos_modelos`
--

DROP TABLE IF EXISTS `vehiculos_modelos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehiculos_modelos` (
  `id_modelo` int NOT NULL AUTO_INCREMENT,
  `id_vehiculo_marca` int NOT NULL,
  `nombre_modelo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_modelo`),
  KEY `fk_vehiculos_modelos_marca` (`id_vehiculo_marca`),
  CONSTRAINT `fk_vehiculos_modelos_marca` FOREIGN KEY (`id_vehiculo_marca`) REFERENCES `vehiculos_marcas` (`id_vehiculo_marca`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehiculos_modelos`
--

LOCK TABLES `vehiculos_modelos` WRITE;
/*!40000 ALTER TABLE `vehiculos_modelos` DISABLE KEYS */;
INSERT INTO `vehiculos_modelos` VALUES (1,1,'Corolla',1),(2,1,'Hilux',1),(3,2,'Focus',1),(4,2,'Ranger',1),(5,3,'Golf',1),(6,3,'Gol',1),(7,4,'Cruze',1),(8,5,'Sandero',1),(9,6,'208',1),(10,7,'Cronos',1),(11,2,'Escort',1),(12,6,'2008',1),(13,8,'Multimarca',1),(14,4,'onix',1),(15,8,'Ford - VW',1);
/*!40000 ALTER TABLE `vehiculos_modelos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'app_taller'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-30 18:32:12

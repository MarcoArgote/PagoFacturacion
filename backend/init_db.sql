USE serviciosdb;
ALTER DATABASE serviciosdb CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Creación de tablas para el sistema de servicios, carrito, usuarios y facturación
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    email VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
    password VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
);

CREATE TABLE IF NOT EXISTS servicios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    descripcion TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    precio DECIMAL(10,2) NOT NULL
);

CREATE TABLE IF NOT EXISTS carritos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

CREATE TABLE IF NOT EXISTS carrito_servicios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carrito_id INT NOT NULL,
    servicio_id INT NOT NULL,
    cantidad INT DEFAULT 1,
    FOREIGN KEY (carrito_id) REFERENCES carritos(id),
    FOREIGN KEY (servicio_id) REFERENCES servicios(id)
);

CREATE TABLE IF NOT EXISTS facturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    datos_cliente JSON NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

CREATE TABLE IF NOT EXISTS factura_servicios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    factura_id INT NOT NULL,
    servicio_id INT NOT NULL,
    cantidad INT DEFAULT 1,
    precio_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (factura_id) REFERENCES facturas(id),
    FOREIGN KEY (servicio_id) REFERENCES servicios(id)
);

-- Usuario de prueba (contraseña: 123456, debe ser hasheada en producción)
INSERT INTO usuarios (nombre, email, password) VALUES
('Usuario Demo', 'demo@correo.com', '$2b$10$Q9Qw1Qw1Qw1Qw1Qw1Qw1QeQw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1');

-- Servicios de laboratorio clínico
INSERT INTO servicios (nombre, descripcion, precio) VALUES
('Biometría Hemática', 'Conteo y análisis de células sanguíneas', 120.00),
('Química Sanguínea 6 elementos', 'Glucosa, Urea, Creatinina, Ácido úrico, Colesterol, Triglicéridos', 320.00),
('Examen General de Orina', 'Análisis físico, químico y microscópico de orina', 90.00),
('Prueba de Embarazo en Sangre', 'Detección de hCG en sangre', 150.00),
('Perfil Tiroideo', 'TSH, T3, T4', 400.00),
('Perfil Hepático', 'Pruebas de función hepática', 350.00),
('Perfil Reumático', 'Pruebas para diagnóstico de enfermedades reumáticas', 380.00),
('Prueba de VIH', 'Detección de anticuerpos contra VIH', 200.00),
('Prueba de COVID-19 PCR', 'Detección molecular de SARS-CoV-2', 950.00),
('Prueba de Antígeno COVID-19', 'Detección rápida de antígeno SARS-CoV-2', 350.00),
('Grupo Sanguíneo y RH', 'Determinación de grupo sanguíneo y factor RH', 100.00),
('Prueba de Glucosa', 'Medición de glucosa en sangre', 90.00),
('Perfil Lipídico', 'Medición de colesterol y triglicéridos', 180.00),
('Análisis de Sangre', 'Examen completo de sangre', 250.00);

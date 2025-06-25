// Estructura inicial de un backend Node.js con Express y conexión a MySQL
const express = require('express');
const mysql = require('mysql2/promise');
const cors = require('cors');
const bcrypt = require('bcrypt');
const jwt = require('jsonwebtoken');

const app = express();
app.use(express.json());
app.use(cors());
app.use((req, res, next) => {
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  next();
});

const dbConfig = {
  host: 'db',
  user: 'user',
  password: 'userpassword',
  database: 'serviciosdb',
  charset: 'utf8mb4',
};

// Forzar encoding utf8mb4 en cada consulta
async function getConnection() {
  const conn = await mysql.createConnection(dbConfig);
  await conn.query("SET NAMES utf8mb4");
  return conn;
}

// Registro de usuario
app.post('/api/register', async (req, res) => {
  const { nombre, email, password } = req.body;
  try {
    const hashedPassword = await bcrypt.hash(password, 10);
    const conn = await mysql.createConnection(dbConfig);
    await conn.execute('INSERT INTO usuarios (nombre, email, password) VALUES (?, ?, ?)', [nombre, email, hashedPassword]);
    await conn.end();
    res.status(201).json({ message: 'Usuario registrado' });
  } catch (err) {
    res.status(400).json({ error: err.message });
  }
});

// Login de usuario
app.post('/api/login', async (req, res) => {
  const { email, password } = req.body;
  try {
    const conn = await mysql.createConnection(dbConfig);
    const [rows] = await conn.execute('SELECT * FROM usuarios WHERE email = ?', [email]);
    await conn.end();
    if (rows.length === 0) return res.status(401).json({ error: 'Usuario no encontrado' });
    const user = rows[0];
    const valid = await bcrypt.compare(password, user.password);
    if (!valid) return res.status(401).json({ error: 'Contraseña incorrecta' });
    const token = jwt.sign({ id: user.id, email: user.email }, 'secreto', { expiresIn: '1d' });
    res.json({ token, nombre: user.nombre });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Obtener servicios
app.get('/api/servicios', async (req, res) => {
  try {
    const conn = await getConnection();
    const [rows] = await conn.execute('SELECT * FROM servicios');
    await conn.end();
    // Forzar utf8 en cada campo por si acaso
    const servicios = rows.map(s => ({
      ...s,
      nombre: Buffer.from(s.nombre, 'binary').toString('utf8'),
      descripcion: Buffer.from(s.descripcion || '', 'binary').toString('utf8')
    }));
    res.json(servicios);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Middleware para autenticar JWT
function auth(req, res, next) {
  const authHeader = req.headers['authorization'];
  if (!authHeader) return res.status(401).json({ error: 'Token requerido' });
  const token = authHeader.split(' ')[1];
  jwt.verify(token, 'secreto', (err, user) => {
    if (err) return res.status(403).json({ error: 'Token inválido' });
    req.user = user;
    next();
  });
}

// Agregar servicio al carrito
app.post('/api/carrito/agregar', auth, async (req, res) => {
  const { servicioId } = req.body;
  const userId = req.user.id;
  try {
    const conn = await mysql.createConnection(dbConfig);
    // Buscar o crear carrito activo
    let [carritos] = await conn.execute('SELECT * FROM carritos WHERE usuario_id = ? ORDER BY id DESC LIMIT 1', [userId]);
    let carritoId;
    if (carritos.length === 0) {
      const [result] = await conn.execute('INSERT INTO carritos (usuario_id) VALUES (?)', [userId]);
      carritoId = result.insertId;
    } else {
      carritoId = carritos[0].id;
    }
    // Agregar servicio al carrito
    await conn.execute('INSERT INTO carrito_servicios (carrito_id, servicio_id, cantidad) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE cantidad = cantidad + 1', [carritoId, servicioId]);
    await conn.end();
    res.json({ message: 'Servicio agregado al carrito' });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Obtener carrito del usuario
app.get('/api/carrito', auth, async (req, res) => {
  const userId = req.user.id;
  try {
    const conn = await mysql.createConnection(dbConfig);
    let [carritos] = await conn.execute('SELECT * FROM carritos WHERE usuario_id = ? ORDER BY id DESC LIMIT 1', [userId]);
    if (carritos.length === 0) return res.json([]);
    const carritoId = carritos[0].id;
    // Obtener los servicios del carrito ordenados, incluyendo el id de carrito_servicios
    const [items] = await conn.execute(`SELECT cs.id, s.nombre, cs.cantidad, s.precio FROM carrito_servicios cs JOIN servicios s ON cs.servicio_id = s.id WHERE cs.carrito_id = ?`, [carritoId]);
    await conn.end();
    res.json(items);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Facturar y vaciar carrito
app.post('/api/facturar', auth, async (req, res) => {
  const userId = req.user.id;
  const datos = req.body.datos;
  const metodoPago = req.body.metodoPago;
  try {
    if (!['tarjeta', 'qr', 'efectivo'].includes(metodoPago)) {
      return res.status(400).json({ error: 'Método de pago inválido' });
    }
    const conn = await mysql.createConnection(dbConfig);
    let [carritos] = await conn.execute('SELECT * FROM carritos WHERE usuario_id = ? ORDER BY id DESC LIMIT 1', [userId]);
    if (carritos.length === 0) return res.status(400).json({ error: 'Carrito vacío' });
    const carritoId = carritos[0].id;
    // Traer todos los datos de los servicios del carrito en una sola consulta
    const [items] = await conn.execute(`SELECT s.id as servicio_id, s.precio, cs.cantidad FROM carrito_servicios cs JOIN servicios s ON cs.servicio_id = s.id WHERE cs.carrito_id = ?`, [carritoId]);
    if (items.length === 0) return res.status(400).json({ error: 'Carrito vacío' });
    let total = 0;
    for (const item of items) {
      if (typeof item.precio === 'undefined') {
        return res.status(400).json({ error: `Servicio con id ${item.servicio_id} no encontrado` });
      }
      total += item.precio * item.cantidad;
    }
    const datosFactura = { ...datos, metodoPago };
    const [facturaRes] = await conn.execute('INSERT INTO facturas (usuario_id, total, datos_cliente) VALUES (?, ?, ?)', [userId, total, JSON.stringify(datosFactura)]);
    const facturaId = facturaRes.insertId;
    for (const item of items) {
      await conn.execute('INSERT INTO factura_servicios (factura_id, servicio_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)', [facturaId, item.servicio_id, item.cantidad, item.precio]);
    }
    await conn.execute('DELETE FROM carrito_servicios WHERE carrito_id = ?', [carritoId]);
    await conn.end();
    res.json({ message: 'Factura generada', facturaId });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Eliminar servicio del carrito por id
app.post('/api/carrito/eliminar', auth, async (req, res) => {
  const userId = req.user.id;
  const id = req.body.id;
  try {
    const conn = await mysql.createConnection(dbConfig);
    // Verifica que el id pertenezca a un carrito del usuario
    let [carritos] = await conn.execute('SELECT * FROM carritos WHERE usuario_id = ? ORDER BY id DESC LIMIT 1', [userId]);
    if (carritos.length === 0) return res.status(400).json({ error: 'Carrito vacío' });
    const carritoId = carritos[0].id;
    // Verifica que el id a eliminar pertenezca al carrito del usuario
    const [rows] = await conn.execute('SELECT * FROM carrito_servicios WHERE id = ? AND carrito_id = ?', [id, carritoId]);
    if (rows.length === 0) return res.status(400).json({ error: 'No autorizado' });
    await conn.execute('DELETE FROM carrito_servicios WHERE id = ?', [id]);
    await conn.end();
    res.json({ message: 'Servicio eliminado' });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Vaciar todo el carrito
app.post('/api/carrito/vaciar', auth, async (req, res) => {
  const userId = req.user.id;
  try {
    const conn = await mysql.createConnection(dbConfig);
    let [carritos] = await conn.execute('SELECT * FROM carritos WHERE usuario_id = ? ORDER BY id DESC LIMIT 1', [userId]);
    if (carritos.length === 0) return res.status(400).json({ error: 'Carrito vacío' });
    const carritoId = carritos[0].id;
    await conn.execute('DELETE FROM carrito_servicios WHERE carrito_id = ?', [carritoId]);
    await conn.end();
    res.json({ message: 'Carrito vaciado' });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.listen(3000, () => {
  console.log('Backend corriendo en puerto 3000');
});

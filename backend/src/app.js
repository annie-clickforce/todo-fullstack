import express from "express";
import session from "express-session";
import connectPgSimple from "connect-pg-simple";
import bcrypt from "bcryptjs";
import pool from "./db.js";

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const PgSession = connectPgSimple(session);

function requireAuth(request, response, next) {
  if (!request.session.userId) {
    return response.status(401).json({ message: "Unauthorized" });
  }
  next();
}

const app = express();

app.use(express.json());

app.use(
  session({
    store: new PgSession({ pool, createTableIfMissing: true }),
    secret: process.env.SESSION_SECRET ?? "dev-session-secret-change-me",
    resave: false,
    saveUninitialized: false,
    cookie: {
      httpOnly: true,
      maxAge: 1000 * 60 * 60 * 24 * 7,
    },
  })
);

app.get("/api/v1/health", (_request, response) => {
  response.json({ status: "ok" });
});

app.post("/api/v1/auth/register", async (request, response) => {
  const { name, email, password } = request.body ?? {};
  const errors = {};

  if (typeof name !== "string" || !name.trim()) {
    errors.name = ["Name is required"];
  }
  if (typeof email !== "string" || !EMAIL_REGEX.test(email)) {
    errors.email = ["Email is invalid"];
  }
  if (typeof password !== "string" || password.length < 8) {
    errors.password = ["Password must be at least 8 characters"];
  }

  if (Object.keys(errors).length > 0) {
    return response.status(422).json({ message: "Validation failed", errors });
  }

  const normalizedEmail = email.toLowerCase();

  const existing = await pool.query("SELECT id FROM users WHERE email = $1", [normalizedEmail]);
  if (existing.rows.length > 0) {
    return response.status(422).json({
      message: "Validation failed",
      errors: { email: ["Email is already registered"] },
    });
  }

  const passwordHash = await bcrypt.hash(password, 10);

  const result = await pool.query(
    `INSERT INTO users (name, email, password_hash)
     VALUES ($1, $2, $3)
     RETURNING id, name, email, created_at AS "createdAt", updated_at AS "updatedAt"`,
    [name.trim(), normalizedEmail, passwordHash]
  );

  response.status(201).json(result.rows[0]);
});

app.post("/api/v1/auth/login", async (request, response) => {
  const { email, password } = request.body ?? {};

  if (typeof email !== "string" || typeof password !== "string") {
    return response.status(401).json({ message: "Invalid email or password" });
  }

  const result = await pool.query(
    `SELECT id, name, email, password_hash,
            created_at AS "createdAt", updated_at AS "updatedAt"
     FROM users
     WHERE email = $1`,
    [email.toLowerCase()]
  );
  const user = result.rows[0];

  if (!user || !(await bcrypt.compare(password, user.password_hash))) {
    return response.status(401).json({ message: "Invalid email or password" });
  }

  request.session.userId = user.id;

  const { password_hash: _passwordHash, ...userWithoutPassword } = user;
  response.json(userWithoutPassword);
});

app.get("/api/v1/auth/me", requireAuth, async (request, response) => {
  const result = await pool.query(
    `SELECT id, name, email, created_at AS "createdAt", updated_at AS "updatedAt"
     FROM users
     WHERE id = $1`,
    [request.session.userId]
  );
  response.json(result.rows[0]);
});

app.get("/api/v1/todos", requireAuth, async (request, response) => {
  const result = await pool.query(
    `SELECT id, title, description, completed,
            created_at AS "createdAt", updated_at AS "updatedAt"
     FROM todos
     WHERE user_id = $1
     ORDER BY created_at DESC`,
    [request.session.userId]
  );
  response.json(result.rows);
});

export default app;

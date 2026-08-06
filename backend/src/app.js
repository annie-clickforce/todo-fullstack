import express from "express";
import bcrypt from "bcryptjs";
import pool from "./db.js";

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const app = express();

app.use(express.json());

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

app.get("/api/v1/todos", async (_request, response) => {
  const result = await pool.query(
    `SELECT id, title, description, completed,
            created_at AS "createdAt", updated_at AS "updatedAt"
     FROM todos
     ORDER BY created_at DESC`
  );
  response.json(result.rows);
});

export default app;

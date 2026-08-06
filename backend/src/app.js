import express from "express";
import pool from "./db.js";

const app = express();

app.use(express.json());

app.get("/api/v1/health", (_request, response) => {
  response.json({ status: "ok" });
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

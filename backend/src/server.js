import app from "./app.js";

const port = Number(process.env.PORT ?? 3011);

app.listen(port, () => {
  console.log(`API listening on port ${port}`);
});

import polka from "polka";
import serveStatic from "serve-static";
import multer from "multer";
import FileParser from "./fileParser.mjs";
import { CombatLog } from "./mongo.mjs";

const { PORT = 8080 } = process.env;
const serve = serveStatic("./public");

const upload = multer({ dest: "uploads/" });

polka()
  .use(serve)
  .get("/logIds", async (req, res) => {
    res.end(JSON.stringify(await CombatLog.distinct("logId")));
  })
  .get("/logsById/:logId", async (req, res) => {
    res.end(JSON.stringify(await CombatLog.find({ logId: req.params.logId })));
  })
  .post("/uploadLog", upload.single("file"), async (req, res) => {
    const fileParser = new FileParser(
      req.file.path,
      req.body.location,
      req.body.username
    );
    await fileParser.loadPowersNames();
    await fileParser.parseFile();
    const persistedLogs = await CombatLog.insertMany(fileParser.parsedLogs);
    res.end(JSON.stringify(persistedLogs));
  })
  .listen(PORT, err => {
    if (err) throw err;
    console.log(`> Running on localhost:${PORT}`);
  });

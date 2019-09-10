import fs from "fs";
import polka from "polka";
import serveStatic from "serve-static";
import multer from "multer";
import bodyParser from "body-parser";
import cors from "cors";
import LogParser from "./logParser.mjs";
import LogsSplitter from "./logsSplitter.mjs";
import { CombatLog, Fight } from "./mongo.mjs";
import { getRelatedFights } from "./queries.mjs";
import FightData from "./fight.mjs";
import POWER_NAMES from "./powerNames.json";

const fsPromises = fs.promises;

const { PORT = 8080 } = process.env;
const serve = serveStatic("./public");

const upload = multer({ dest: "uploads/" });

polka()
  .use(cors())
  .use(serve)
  .use(bodyParser.json())
  .get("/logsIds", async (req, res) => {
    res.end(JSON.stringify(await CombatLog.distinct("logId")));
  })
  .get("/logsById/:logId", async (req, res) => {
    res.end(JSON.stringify(await CombatLog.find({ logId: req.params.logId })));
  })
  .get("/lastFights", async (req, res) => {
    const fights = await Fight.find({ published: true }, null, {
      limit: 10,
      sort: { datetimeStart: -1 }
    });
    res.end(JSON.stringify(fights));
  })
  .post("/uploadLog", upload.single("file"), async (req, res) => {
    const file = await fsPromises.readFile(req.file.path, { encoding: "utf8" });
    const logs = file
      .split("\n")
      .map(log => {
        const logParser = new LogParser(
          log,
          req.body.location,
          req.body.username,
          POWER_NAMES
        );
        logParser.parse();
        return logParser.getDBData();
      })
      .filter(log => log.skillAmount);

    const logsSplitter = new LogsSplitter({ logs });
    const fights = await logsSplitter.splitByFights();
    const persistedFights = await Fight.insertMany(fights);

    // const persistedLogs = await CombatLog.insertMany(logs);
    res.end(JSON.stringify(persistedFights));
  })
  .post("/saveFights", async (req, res) => {
    await Fight.bulkWrite(
      req.body.locations.map(({ _id, location }) => ({
        updateOne: {
          filter: { _id },
          update: { location, published: true }
        }
      }))
    );

    const mergedFights = await Promise.all(
      req.body.locations.map(async ({ _id, location }) => {
        const relatedFights = await getRelatedFights(_id);

        if (relatedFights.length <= 1) {
          return relatedFights[0];
        }

        const agregatedFight = new FightData(relatedFights[0].datetimeStart);
        agregatedFight.location = location;
        relatedFights.forEach(fight =>
          fight.logs.forEach(log => agregatedFight.addLog(log))
        );

        await Fight.deleteMany({
          _id: { $in: relatedFights.map(fight => fight._id) }
        });

        return await new Fight({
          ...agregatedFight.getDBData(),
          published: true
        }).save();
      })
    );

    res.end(JSON.stringify(mergedFights));
  })
  .post("/updateLocation", async (req, res) => {
    res.end(
      JSON.stringify(
        (await Fight.findByIdAndUpdate(
          req.body._id,
          {
            location: req.body.location
          },
          { new: true }
        )).location
      )
    );
  })
  .listen(PORT, err => {
    if (err) throw err;
    console.log(`> Running on localhost:${PORT}`);
  });

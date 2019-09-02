import mongoose from "mongoose";
import fs from "fs";
import LogParser from "./logParser.mjs";
import Fight from "./fight.mjs";

const fsPromises = fs.promises;

export default class FileParser {
  constructor({
    filepath,
    location,
    username,
    logId = new mongoose.Types.ObjectId(),
    powersPath = "./crowfall-data/data/power",
    savePowersNames = false,
    saveUrecognizedSkills = true,
    predefinedPowers = [
      // passive Toxins (see Diffusion)
      "poison toxin",
      "disease toxin",
      "nature toxin"
    ],
    fightsThreshold = 1000 * 5 * 60
  }) {
    this.filepath = filepath;
    this.powersPath = powersPath;
    this.powerNames = predefinedPowers;
    this.savePowersNames = savePowersNames;
    this.saveUrecognizedSkills = saveUrecognizedSkills;
    this.parsedLogs = [];
    this.location = location;
    this.username = username;
    this.logId = logId;
    this.fightsThreshold = fightsThreshold;
  }

  async parseFile() {
    const logs = await fsPromises.readFile(this.filepath, { encoding: "utf8" });
    const unrecognizedSkills = new Set();
    Promise.all(
      logs.split("\n").map(log => {
        const logParser = new LogParser(
          log,
          this.location,
          this.username,
          this.powerNames,
          unrecognizedSkills
        );
        logParser.parse();
        this.parsedLogs.push(logParser.getDBData());
      })
    );
    if (this.saveUrecognizedSkills) {
      await fsPromises.writeFile(
        "./unrecognizedSkills.json",
        JSON.stringify(Array.from(unrecognizedSkills), null, 2)
      );
    }
  }

  // method not ready, work in progress
  async splitByFights() {
    if (!this.parsedLogs.length) {
      console.warn("try to split by fights but parsed logs are empty");
      return;
    }

    this.parsedLogs = this.parsedLogs.map(log => ({
      ...log,
      dateTime: new Date(log.dateTime)
    }));

    const fights = [];
    let currentFight = new Fight(this.parsedLogs[0].dateTime);
    for (let ii = 0; ii < this.parsedLogs.length; ii++) {
      const log = this.parsedLogs[ii];
      // const dateDelta = log.dateTime.getTime() - currentFight.datetimeEnd.getTime();
      // if (dateDelta < this.fightsThreshold) {
      if (
        log.dateTime.getTime() - currentFight.datetimeEnd.getTime() <
          this.fightsThreshold &&
        currentFight.datetimeStart.getTime() - log.dateTime.getTime() <
          this.fightsThreshold
      ) {
        currentFight.addLog(log);
      } else {
        fights.push(currentFight);
        currentFight = new Fight(log.dateTime);
      }
    }
    console.log(
      fights.map(
        fight =>
          `${fight.datetimeStart} - ${fight.datetimeEnd} - ${fight.logs.length}`
      )
    );
    return fights;
  }

  async loadPowersNames() {
    const powerFiles = await fsPromises.readdir(this.powersPath);

    await Promise.all(
      powerFiles.map(async powerFile => {
        const path = `${this.powersPath}/${powerFile}`;
        try {
          const file = await fsPromises.readFile(path, { encoding: "utf8" });
          const power = JSON.parse(file);
          if (power.name) {
            this.powerNames.push(
              power.name
                .replace(/ III$/, " 3")
                .replace(/ II$/, " 2")
                .replace(/ I$/, " 1")
                .toLowerCase()
            );
          }
        } catch (err) {
          console.warn(
            `[WARN] cannot read or get data from file: ${path}`,
            err
          );
        }
      })
    );

    if (this.savePowersNames) {
      fsPromises.writeFile(
        "./tmp.json",
        JSON.stringify(this.powerNames, null, 2)
      );
    }
  }
}

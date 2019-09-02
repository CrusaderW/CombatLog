const fs = require('fs').promises;

const powersPath = "./backend/crowfall-data/data/power";
const outputPaths = [
  './frontend/src/powerNames.json',
  './backend/powerNames.json',
];

(async () => {
  const powerFiles = await fs.readdir(powersPath);
  const powerNames = [
    // passive Toxins (see Diffusion)
    "poison toxin",
    "disease toxin",
    "nature toxin"
  ];
  await Promise.all(
    powerFiles.map(async powerFile => {
      const path = `${powersPath}/${powerFile}`;
      try {
        const file = await fs.readFile(path, { encoding: "utf8" });
        const power = JSON.parse(file);
        if (power.name) {
          powerNames.push(
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

  outputPaths.forEach(path => fs.writeFile(path, JSON.stringify(powerNames, null, 2)));
})()
import multer from 'multer'
import fs from 'fs'
import { Fight } from '../../models/fight.js'
import LogParser from '../../logParser.mjs'
import LogsSplitter from '../../logsSplitter.mjs'
import { getRelatedFights, getLastFights } from '../../queries.mjs'
import POWER_NAMES from '../../powerNames.json'
import FightData from '../../fight.mjs'

const upload = multer({ dest: 'uploads/' })
const fsPromises = fs.promises

const mergeFight = async ({ _id, location }) => {
  const relatedFights = await getRelatedFights(_id)

  if (relatedFights.length <= 1) {
    return relatedFights[0]
  }

  const agregatedFight = new FightData(relatedFights[0].datetimeStart)
  agregatedFight.location = location
  relatedFights.forEach(fight =>
    fight.logs.forEach(log => agregatedFight.addLog(log))
  )

  await Fight.deleteMany({
    _id: { $in: relatedFights.map(fight => fight._id) }
  })

  return new Fight({
    ...agregatedFight.getDBData(),
    published: true
  }).save()
}

export default app => {
  app.get('/lastFights', async (req, res) => {
    res.end(JSON.stringify(await getLastFights()))
  })

  app.post('/uploadLog', upload.single('file'), async (req, res) => {
    const file = await fsPromises.readFile(req.file.path, { encoding: 'utf8' })
    const logs = file
      .split('\n')
      .map(log => {
        const logParser = new LogParser(
          log,
          req.body.location,
          req.body.username,
          POWER_NAMES
        )
        logParser.parse()
        return logParser.getDBData()
      })
      .filter(log => log.skillAmount)

    const logsSplitter = new LogsSplitter({ logs })

    const fights = await logsSplitter.splitByFights()

    const persistedFights = await Fight.insertMany(fights)

    // const persistedLogs = await CombatLog.insertMany(logs);
    res.end(JSON.stringify(persistedFights))
  })

  app.post('/saveFights', async (req, res) => {
    await Fight.bulkWrite(
      req.body.locations.map(({ _id, location }) => ({
        updateOne: {
          filter: { _id },
          update: { location, published: true }
        }
      }))
    )

    const mergedFights = await Promise.all(req.body.locations.map(mergeFight))

    res.end(JSON.stringify(mergedFights))
  })

  app.post('/updateLocation', async (req, res) => {
    const { _id, location } = req.body
    await Fight.findByIdAndUpdate(
      req.body._id,
      {
        location: req.body.location
      },
      { new: true }
    )
    await mergeFight({ _id, location })

    res.end(JSON.stringify(await getLastFights()))
  })

  app.delete('/deleteFight', async (req, res) => {
    try {
      await Fight.deleteOne({ _id: req.body._id })
      res.end(JSON.stringify({ success: true }))
    } catch (err) {
      res.end(JSON.stringify({ success: false, err }))
    }
  })
}

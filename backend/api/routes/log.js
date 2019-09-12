import { CombatLog } from '../../models/log.js'

export default app => {
  app.get('/logsIds', async (_, res) => {
    res.end(JSON.stringify(await CombatLog.distinct('logId')))
  })

  app.get('/logsById/:logId', async (req, res) => {
    res.end(JSON.stringify(await CombatLog.find({ logId: req.params.logId })))
  })
}

import setupLogApi from './routes/log.js'
import setupFightApi from './routes/fight.js'

export const setupApiRoutes = app => {
  setupLogApi(app)
  setupFightApi(app)
}

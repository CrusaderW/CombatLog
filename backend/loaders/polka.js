import bodyParser from 'body-parser'
import cors from 'cors'
import { setupApiRoutes } from '../api/index.js'
import serveStatic from 'serve-static'
import './mongoose.js'

const serve = serveStatic('./public')

export default ({ app }) => {
  /**
   * Health Check endpoints
   */
  app.get('/status', (_, res) => {
    res.end()
  })
  app.head('/status', (_, res) => {
    res.end()
  })

  app.use(cors())

  app.use(serve)

  app.use(bodyParser.json())

  setupApiRoutes(app)
}

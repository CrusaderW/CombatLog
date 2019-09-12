import polkaLoader from './polka.js'
import Logger from './logger.js'
import dependencyInjectorLoader from './dependencyInjector.js'
import mongooseLoader from './mongoose.js'

export default async ({ app }) => {
  await mongooseLoader()
  Logger.info('✌️ DB loaded and connected!')

  await dependencyInjectorLoader()
  Logger.info('✌️ Dependency Injector loaded')

  await polkaLoader({ app: app })
  Logger.info('✌️ Express loaded')
}

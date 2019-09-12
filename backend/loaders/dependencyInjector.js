import typedi from 'typedi'
import LoggerInstance from './logger.js'

const { Container } = typedi

export default () => {
  try {
    Container.set('logger', LoggerInstance)
  } catch (e) {
    LoggerInstance.error('🔥 Error on dependency injector loader: %o', e)
    throw e
  }
}

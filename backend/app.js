import polka from 'polka'
import loaders from './loaders/index.js'


const { PORT = 8080 } = process.env

const startServer = async () => {
  const app = polka()

  await loaders({ app })

  app.listen(PORT, err => {
    if (err) {
      process.exit(1)
    }
    console.log(`
      ################################################
      🛡️  Server listening on port: ${PORT} 🛡️
      ################################################
    `)
  })
}

startServer()

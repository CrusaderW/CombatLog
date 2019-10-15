import envalid from 'envalid'

export default envalid.cleanEnv(
    process.env,
    {
        MONGO_URL: envalid.url({ default: 'mongodb://localhost:27017/admin' })
    },
    { strict: true }
)
import { join } from 'path';
import polka from 'polka';
import mongoose from 'mongoose'
import serveStatic from 'serve-static';
import multer from 'multer';
import { parseFile } from './combatLogParser';
import { CombatLog } from './mongo.mjs';
import { dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));

const { PORT = 8080 } = process.env;
const serve = serveStatic(join(__dirname, 'public'));

const upload = multer({ dest: 'uploads/' })

polka()
  .use(serve)
  .post('/uploadLog', upload.single('file'), async (req, res) => {
    const parsedLog = await parseFile(req.file.path);
    const logId = new mongoose.Types.ObjectId();
    const logWithId = parsedLog.map(log => ({ ...log, logId }));
    const persistedLogs = await CombatLog.insertMany(logWithId);
    res.end(JSON.stringify(persistedLogs));
  })
  .listen(PORT, err => {
    if (err) throw err;
    console.log(`> Running on localhost:${PORT}`);
  });
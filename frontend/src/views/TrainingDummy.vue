<template>
  <div>
    <h1>Training Dummy page</h1>
    <el-row :gutter="20">
      <el-col :span="10" :offset="7">
        <div>
          <el-input v-model="username" placeholder="username"></el-input>
        </div>
        <div style="margin-top: 15px;">
          <el-input v-model="location" placeholder="location"></el-input>
        </div>

        <div style="margin-top: 15px;">
          <h2>Select an log file</h2>
          <el-upload :on-remove="removeFile" :on-change="onFileChange" :auto-upload="false">
            <el-button slot="trigger" size="small" type="primary">Choose log file</el-button>
          </el-upload>
        </div>
      </el-col>
    </el-row>
  </div>
</template>

<script>
import LogParser from "../logParser";
import POWER_NAMES from "../powerNames.json";

export default {
  name: "Upload",
  data() {
    return {
      file: null,
      username: "",
      location: "",
      logs: []
    };
  },
  methods: {
    async onFileChange(file, fileList) {
      this.file = await file.raw.text();
      this.logs = this.file.split("\n").map(log => {
        const logParser = new LogParser(
          log,
          this.location,
          this.username,
          POWER_NAMES
        );
        logParser.parse();
        return logParser.getDBData();
      });

      console.log(this.logs);
    },
    removeFile() {
      this.file = null;
    }
  }
};
</script>

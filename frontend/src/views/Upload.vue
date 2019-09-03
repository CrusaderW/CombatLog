<template>
  <div class="upload">
    <h1>Upload page</h1>
    <el-row :gutter="20">
      <el-col :span="10" :offset="7">
        <div>
          <el-input v-model="username" placeholder="username"></el-input>
        </div>
        <div style="margin-top: 15px;">
          <el-input v-model="location" placeholder="location"></el-input>
        </div>

        <div>
          <h2>Select an log file</h2>
          <el-upload
            action="tmp"
            :on-remove="removeFile"
            :on-change="onFileChange"
            :auto-upload="false"
          >
            <el-button slot="trigger" size="small" type="primary">Choose log file</el-button>
            <el-button v-show="file" size="small" type="success" @click="submitFile">Upload</el-button>
          </el-upload>
        </div>
      </el-col>
    </el-row>
  </div>
</template>

<script>
export default {
  name: "Upload",
  data() {
    return {
      file: null,
      username: "",
      location: ""
    };
  },
  methods: {
    onFileChange(file, fileList) {
      this.file = file.raw;
    },
    async submitFile() {
      const form = new FormData();
      form.append("file", this.file);
      form.append("username", this.username);
      form.append("location", this.location);

      const res = await fetch("/uploadLog", {
        method: "POST",
        body: form
      });
      const data = await res.json();
      console.log(data);
    },
    removeFile() {
      this.file = null;
    }
  }
};
</script>

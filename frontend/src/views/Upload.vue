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
          <el-upload :on-remove="removeFile" :http-request="submitFile">
            <el-button size="small" type="primary">Click to upload</el-button>
          </el-upload>

          <div v-if="file">
            <button @click="submitFile">Send file</button>
            <button @click="removeFile">Remove file</button>
          </div>
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
    onFileChange(event) {
      console.log(event.target);
      console.log(event.target.files);

      this.file = event.target.files[0];
    },
    async submitFile(event) {
      const form = new FormData();
      form.append("file", event.file);
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

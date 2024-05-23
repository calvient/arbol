import {Box} from '@calvient/decal';
import {Bar, BarChart, ResponsiveContainer, Tooltip, XAxis, YAxis, CartesianGrid} from 'recharts';

interface BarChartFormatProps {
  data: Array<{name: string; value: number}>;
}

const BarFormat = ({data}: BarChartFormatProps) => {
  return (
    <Box mt={4} w={'full'} h={'400px'}>
      <ResponsiveContainer width='100%' height='100%'>
        <BarChart width={400} height={400} data={data}>
          <CartesianGrid strokeDasharray='3 3' />
          <XAxis dataKey='name' />
          <YAxis />
          <Tooltip />
          <Bar dataKey='value' fill='#6C6897' />
        </BarChart>
      </ResponsiveContainer>
    </Box>
  );
};

export default BarFormat;
